<?php
/**
 * @Author: Sebastian Castro
 * @Date:   2017-06-18 21:03:01
 * @Last Modified by:   Sebastian Castro
 * @Last Modified time: 2018-07-08 16:46:11
 */

namespace App\EventListener;

use App\Application\Sonata\UserBundle\Document\Group;
use App\Document\Element;
use App\Document\TileLayer;
use App\Document\Import;
use App\Document\ImportDynamic;
use App\Document\ModerationState;
use App\Document\Option;
use App\Document\Webhook;
use App\Services\AsyncService;
use App\Services\DocumentManagerFactory;
/* check database integrity : for example when removing an option, need to remove all references to this options */
class DatabaseIntegrityWatcher
{
    protected $asyncService;
    protected $config;

    public function __construct(AsyncService $asyncService, DocumentManagerFactory $dmFactory)
    {
        $this->asyncService = $asyncService;
        $this->dmFactory = $dmFactory;
    }

    public function getConfig($dm)
    {
        if (!$this->config) {
            $this->config = $dm->get('Configuration')->findConfiguration();
        }
        return $this->config;
    }

    public function documentNotFound(\Doctrine\ODM\MongoDB\Event\DocumentNotFoundEventArgs $eventArgs): void
    {
        // In case our DbIntegrity fails, prevent the documentNotFound exception from being thrown
        $eventArgs->disableException();
    }

    public function postPersist(\Doctrine\ODM\MongoDB\Event\LifecycleEventArgs $eventArgs): void
    {
        $document = $eventArgs->getDocument();
        if ($document instanceof Webhook) {
            $rootDm = $this->dmFactory->getRootManager();
            $rootDm->query('Project')->updateOne()
                ->field('domainName')->equals($this->dmFactory->getCurrentDbName())
                ->field('haveWebhooks')->set(true)
                ->execute();
        }
        if ($document instanceof ImportDynamic) {
            if ($document->getIsSynchronized())
                $this->updateWebhookConfig();
        }
    }

    // use post remove instead?
    public function preRemove(\Doctrine\ODM\MongoDB\Event\LifecycleEventArgs $args)
    {
        $document = $args->getDocument();
        $dm = $args->getDocumentManager();
        if ($document instanceof Group) {
            $group = $document;
            $qb = $dm->get('User')->createQueryBuilder();
            $users = $qb->field('groups.id')->in([$group->getId()])->execute();
            if ($users->count() > 0) {
                foreach ($users as $user) {
                    $user->removeGroup($group);
                }
            }
        } elseif ($document instanceof Webhook) {
            $webhook = $document;
            $contributions = $dm->query('UserInteractionContribution')
                                ->field('webhookPosts.webhook.$id')->equals($webhook->getId())
                                ->execute();

            foreach ($contributions as $contrib) {
                $contrib->getElement()->setPreventJsonUpdate(true);
                foreach ($contrib->getWebhookPosts() as $post) {
                    if ($post->getWebhook() && $post->getWebhook()->getId() == $webhook->getId()) {
                        $contrib->removeWebhookPost($post);
                    }
                }
            }
        } elseif ($document instanceof Element) {
            // remove dependance from many to many relations
            $qb = $dm->query('Element');
            $dependantElements = $qb
                ->addOr($qb->expr()->field('nonDuplicates.$id')->equals($document->getId()))
                ->addOr($qb->expr()->field('potentialDuplicates.$id')->equals($document->getId()))
                ->addOr($qb->expr()->field('aggregatedElements.$id')->equals($document->getId()))
                ->execute(); 
            foreach ($dependantElements as $element) {
                $element->removeNonDuplicate($document);
                $element->removePotentialDuplicate($document);
                $element->removeAggregatedElement($document);
            }
            foreach ($document->getPotentialDuplicates() as $element) {
                $element->setModerationState(ModerationState::NotNeeded);
            }

            // remove depency for elements fields
            $elementsFields = [];
            $config = $this->getConfig($dm);
            foreach ($config->getElementFormFields() as $field) {
                if ($field->type == 'elements') $elementsFields[] = $field->name;
            }
            if (count($elementsFields)) {
                foreach ($elementsFields as $fieldName) {
                    $fieldPath = "data.$fieldName.{$document->getId()}";
                    $dependantElementsIds = $dm->query('Element')->field($fieldPath)->exists(true)->getIds();

                    if (count($dependantElementsIds)) {
                        $dm->query('Element')->updateMany()
                           ->field($fieldPath)->unsetField()->exists(true)
                           ->execute();
                        $elementIdsString = '"'.implode(',', $dependantElementsIds).'"';
                        $this->asyncService->callCommand('app:elements:updateJson', ['ids' => $elementIdsString]);
                    }
                }
            }
        } elseif ($document instanceof TileLayer) {
            $config = $this->getConfig($dm);
            if ($config->getDefaultTileLayer()->getId() == $document->getId()) {
                $config->setDefaultTileLayer(null);
            }
        }
    }

    public function postRemove(\Doctrine\ODM\MongoDB\Event\LifecycleEventArgs $args): void
    {
        $document = $args->getDocument();
        if ($document instanceof Import || $document instanceof ImportDynamic) {
            if (!array_key_exists('delete_import_and_keep_elements', $_SESSION)|| $_SESSION['delete_import_and_keep_elements'] !== true) {
                $this->asyncService->callCommand('app:import:remove', ['importId' => $document->getId()]);
            }
            unset($_SESSION['delete_import_and_keep_elements']);
        }
    }

    public function preUpdate(\Doctrine\ODM\MongoDB\Event\LifecycleEventArgs $args)
    {
        $document = $args->getDocument();
        $dm = $args->getDocumentManager();

        // When changing the name of one Option, we need to update json representation of every element
        // using this option
        if ($document instanceof Option) {
            $changeset = $dm->getChangeSet($document);
            if (array_key_exists('name', $changeset)) {
                $elementIds = $dm->query('Element')
                                 ->field('optionValues.optionId')->in([$document->getId()])
                                 ->getIds();
                if (count($elementIds)) {
                    $elementIdsString = '"'.implode(',', $elementIds).'"';
                    $this->asyncService->callCommand('app:elements:updateJson', ['ids' => $elementIdsString]);
                }
            }
        }
        if ($document instanceof Import) {
            // Detect if main import config have been changed, so we need to collect the data again
            $changeset = $dm->getChangeSet($document);
            $somethingChanged = false;
            if (array_intersect(array_keys($changeset), ["customCode", "optionsToAddToEachElement", "url", "sourceType", "file", "osmQueriesJson"])) {
                $somethingChanged = true;
            }
            if (!$somethingChanged && array_key_exists('ontologyMapping', $changeset)) {
                $old = $changeset['ontologyMapping'][0];
                $new = $changeset['ontologyMapping'][1];
                if (!$old || !$new) {
                    $somethingChanged = true;
                } else {
                    $old = array_map(function($a) { return $a["mappedProperty"]; }, $old);
                    $new = array_map(function($a) { return $a["mappedProperty"]; }, $new);
                    $old = array_filter($old, function($a) { return $a && $a != '/'; });
                    $new = array_filter($new, function($a) { return $a && $a != '/'; });
                    $somethingChanged = $old != $new;
                }
            }
            if ($somethingChanged) {
                $document->setMainConfigUpdatedAt(time());
            }
        }
        if ($document instanceof ImportDynamic) {
            // Update sourceName in element
            $changeset = $dm->getChangeSet($document);
            if (array_key_exists('sourceName', $changeset)) {
                $dm->query('Element')->updateMany()                
                                 ->field('sourceKey')->set($changeset['sourceName'][1])
                                 ->field('source')->references($document)
                                 ->execute();
                $elementIds = $dm->query('Element')
                                 ->field('source')->references($document)
                                 ->getIds();
                if (count($elementIds)) {
                    $elementIdsString = '"'.implode(',', $elementIds).'"';
                    $this->asyncService->callCommand('app:elements:updateJson', ['ids' => $elementIdsString]);
                }
            }
            if (array_key_exists('isSynchronized', $changeset) && $changeset['isSynchronized'][1]) {
                $this->updateWebhookConfig();
            }
        }
        if ($document instanceof Element) {
            $changeset = $dm->getChangeSet($document);

            if ($document->isPending() && array_key_exists('optionsString', $changeset)) {
                $old = $changeset['optionsString'][0];
                $new = $changeset['optionsString'][1];
                if ($old != '' && $new == '')
                    throw new \Exception("Reseting categories of a pending element is not allowed
                        DB: {$this->dmFactory->getCurrentDbName()}
                        NAME: {$document->getName()}
                        ID: {$document->getId()}
                        OLD_optionsString: $old");
            }
        }
    }

    private function updateWebhookConfig()
    {
        $rootDm = $this->dmFactory->getRootManager();
        $rootDm->query('Project')->updateOne()
            ->field('domainName')->equals($this->dmFactory->getCurrentDbName())
            ->field('haveWebhooks')->set(true)
            ->execute();
    }

    public function postFlush(\Doctrine\ODM\MongoDB\Event\PostFlushEventArgs $eventArgs)
    {
        $dm = $eventArgs->getDocumentManager();

        $documentManaged = $dm->getUnitOfWork()->getIdentityMap();

        if (array_key_exists("App\Document\Element", $documentManaged)) {
            $this->updateLinksBetweenElements($documentManaged["App\Document\Element"], $dm);
        }

        // On option delete
        $optionsDeleted = array_filter($dm->getUnitOfWork()->getScheduledDocumentDeletions(), function ($doc) { return $doc instanceof Option; });
        if (count($optionsDeleted) > 0) {
            $optionsIdDeleted = array_map(function ($option) { return $option->getId(); }, $optionsDeleted);
            $this->asyncService->callCommand('app:elements:removeOptions', ['ids' => implode(',', $optionsIdDeleted)]);
        }
    }

    private function updateLinksBetweenElements($elements, $dm)
    {
        $elementsFields = [];
        $bidirdectionalElementsFields = [];
        $config = $this->getConfig($dm);
        foreach ($config->getElementFormFields() as $field) {
            if ($field->type == 'elements') {
                $elementsFields[] = $field->name;
                if (isset($field->reversedBy)) $bidirdectionalElementsFields[] = $field;
            }
        }

        $elementIdsToUpdate = [];

        foreach ($elements as $element) {

            if ($element->getPreventLinksUpdate()) return;

            // Fixs elements referencing this element            
            if (count($elementsFields)) {
                $changeset = $dm->getChangeSet($element);

                // If name have changed, update element which reference this element
                if (array_key_exists('name', $changeset)) {
                    $newName = $changeset['name'][1];
                    foreach ($elementsFields as $fieldName) {
                        $fieldPath = "data.$fieldName.{$element->getId()}";
                        $dm->query('Element')->updateMany()
                                    ->field($fieldPath)->set($newName)
                                    ->field($fieldPath)->exists(true)
                                    ->execute();
                        $elementIdsToUpdate = array_merge($elementIdsToUpdate, $dm->query('Element')
                                    ->field($fieldPath)->exists(true)->getIds());

                    }
                }
                // If bidirectional element field have changed, update reverse relation
                // exple A { parent: B }, we should auto update B { children: A }
                if (array_key_exists('data', $changeset)) {
                    foreach ($bidirdectionalElementsFields as $field) {                        
                        $changes = $changeset['data'];
                        $oldValue = $changes[0] && isset($changes[0][$field->name]) ? array_keys((array) $changes[0][$field->name]) : [];
                        $oldValue = array_map(fn($s) => strval($s), $oldValue);
                        $newValue = $changes[1] && isset($changes[1][$field->name]) ? array_keys((array) $changes[1][$field->name]) : [];
                        $newValue = array_map(fn($s) => strval($s), $newValue);
                        $removedElements = array_diff($oldValue, $newValue);
                        $addedElements = array_diff($newValue, $oldValue);

                        $fieldPath = "data.{$field->reversedBy}.{$element->getId()}";

                        // Updates elements throught reverse relation
                        if (count($addedElements) > 0) {
                            $dm->query('Element')
                                    ->updateMany()
                                    ->field('id')->in($addedElements)
                                    ->field("data.{$field->reversedBy}")->unsetField()->in([[], ""])
                                    ->execute();
                            $dm->query('Element')
                                    ->updateMany()
                                    ->field('id')->in($addedElements)
                                    ->field($fieldPath)->set($element->getName())
                                    ->execute();
                            $elementIdsToUpdate = array_merge($elementIdsToUpdate, $dm->query('Element')
                                    ->field('id')->in($addedElements)->getIds());
                        }
                        if (count($removedElements) > 0) {
                            $dm->query('Element')
                                    ->updateMany()
                                    ->field('id')->in($removedElements)
                                    ->field("data.{$field->reversedBy}")->unsetField()->in([[], ""])
                                    ->execute();
                            $dm->query('Element')
                                    ->updateMany()
                                    ->field('id')->in($removedElements)
                                    ->field($fieldPath)->unsetField()->exists(true)
                                    ->execute();
                            $elementIdsToUpdate = array_merge($elementIdsToUpdate, $dm->query('Element')
                                    ->field('id')->in($removedElements)->getIds());
                        }
                    }
                }                
            }
        }

        if (count($elementIdsToUpdate)) {
            $ids = array_unique($elementIdsToUpdate);
            $elementIdsString = '"'.implode(',', $ids).'"';
            $this->asyncService->callCommand('app:elements:updateJson', ['ids' => $elementIdsString]);
        }
    }
}
