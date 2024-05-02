<?php

namespace App\EventListener;

use App\Document\Configuration\ConfigurationApi;
use App\Document\Configuration\ConfigurationSubscription;
use App\Document\Configuration;
use App\Document\Configuration\ConfigurationMarker;
use App\Document\Configuration\ConfigurationMenu;
use App\Services\AsyncService;
use App\Services\DocumentManagerFactory;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Doctrine\ODM\MongoDB\DocumentManager;

class ConfigurationListener
{
    protected $asyncService;
    protected $needUpdateElementJsonOnNextFlush;

    public function __construct(AsyncService $asyncService, DocumentManager $dm,
                                DocumentManagerFactory $dmFactory,
                                $baseUrl, $contactEmail, $projectDir)
    {
        $this->asyncService = $asyncService;
        $this->baseUrl = $baseUrl;
        $this->contactEmail = $contactEmail;
        $this->projectDir = $projectDir;
        $this->dm = $dm;
        $this->dmFactory = $dmFactory;
    }

    public function onKernelRequest(RequestEvent $event)
    {
        $request = $event->getRequest();
        // some logic to determine the $locale
        $config = $this->dm->get('Configuration')->findConfiguration();
        $locale = $config ? $config->getLocale() : 'en';
        $request->setLocale($locale);
    }

    public function preUpdate(\Doctrine\ODM\MongoDB\Event\LifecycleEventArgs $args)
    {
        $document = $args->getDocument();
        
        if (  ! $document instanceof ConfigurationApi
           && ! $document instanceof ConfigurationSubscription
           && ! $document instanceof ConfigurationMarker
           && ! $document instanceof ConfigurationMarker
           && ! $document instanceof ConfigurationMenu
           && ! $document instanceof Configuration ) {
            return;
        }
        
        $dm = $args->getDocumentManager();
        $changeset = $dm->getChangeSet($document);

        // Update Json representation to fit the private property config
        if (  $document instanceof ConfigurationApi    && array_key_exists('publicApiPrivateProperties', $changeset)
           || $document instanceof ConfigurationSubscription && array_key_exists('subscriptionProperties', $changeset)
           || $document instanceof ConfigurationMarker && array_key_exists('fieldsUsedByTemplate', $changeset)
           || $document instanceof ConfigurationMarker && array_key_exists('displayPopup', $changeset)
           || $document instanceof ConfigurationMenu   && array_key_exists('filtersJson', $changeset)
           || $document instanceof Configuration       && array_key_exists('refreshNeededMail', $changeset)
           || $document instanceof Configuration       && array_key_exists('refreshMuchNeededMail', $changeset)
           || $document instanceof Configuration       && array_key_exists('maxDaysBeforeSendingRefreshNeededMail', $changeset)
           || $document instanceof Configuration       && array_key_exists('maxDaysBeforeSendingRefreshMuchNeededMail', $changeset)) {
            $this->needUpdateElementJsonOnNextFlush = true;
        }

        if ($document instanceof Configuration) {
            if (array_key_exists('elementFormFieldsJson', $changeset)) {
                $formFieldsChanged = $changeset['elementFormFieldsJson'];
                $oldFormFields = $formFieldsChanged[0];
                $newFormFields = $formFieldsChanged[1];
                $this->updateSearchIndex($dm, $oldFormFields, $newFormFields);
            }
            if (array_key_exists('locale', $changeset)) {
                $this->manuallyUpdateIndex($dm);
            }
            if (array_key_exists('customDomain', $changeset)) {
                $customDomainChanged = $changeset['customDomain'];
                $oldCustomDomain = rtrim(preg_replace('/https?:\/\//', '', $customDomainChanged[0]), '/');
                $newCustomDomain = rtrim(preg_replace('/https?:\/\//', '', $customDomainChanged[1]), '/');
                $filesystem = new Filesystem();
                // Those files will be consumed by bin/execute_custom_domain.sh called by a cron tab
                $removePath = "$this->projectDir/var/file_queues/custom_domain_to_remove";
                $addPath = "$this->projectDir/var/file_queues/custom_domain_to_configure";
                if ($oldCustomDomain) {
                    $filesystem->dumpFile("$removePath/{$document->getDbName()}", $oldCustomDomain);
                }
                if  ($newCustomDomain) {
                    $gogo_url = $document->getDbName() . '.' . $this->baseUrl;
                    $filesystem->dumpFile("$addPath/{$document->getDbName()}", "$newCustomDomain $gogo_url $this->contactEmail");
                }
            }
            if (array_key_exists('publishOnSaasPage', $changeset)) {
                $rootDm = $this->dmFactory->getRootManager();
                $rootDm->query('Project')->updateOne()
                    ->field('domainName')->equals($this->dmFactory->getCurrentDbName())
                    ->field('published')->set($document->getPublishOnSaasPage())
                    ->execute();
            }
        }
    }

    public function postFlush(\Doctrine\ODM\MongoDB\Event\PostFlushEventArgs $eventArgs)
    {
        if ($this->needUpdateElementJsonOnNextFlush) {
            $this->needUpdateElementJsonOnNextFlush = false;
            $this->asyncService->callCommand('app:elements:updateJson', ['ids' => 'all']);
        }
    }

    public function manuallyUpdateIndex($dm)
    {
        $formFields = $dm->get('Configuration')->findConfiguration()->getElementFormFieldsJson();
        $this->commitIndex($dm, $this->calculateSearchIndexConfig($formFields));
    }

    private function updateSearchIndex($dm, $oldFormFields, $newFormFields) {
        if ($oldFormFields == null || $newFormFields == null) return;
        $oldSearchIndex = $this->calculateSearchIndexConfig($oldFormFields);
        $newSearchIndex = $this->calculateSearchIndexConfig($newFormFields);

        if ($oldSearchIndex != $newSearchIndex) {
            $this->commitIndex($dm, $newSearchIndex);
        }
    }

    private function commitIndex($dm, $indexConf)
    {
        $db = $dm->getDB();
        $db->command(["deleteIndexes" => 'Element',"index" => "name_text"]);
        $db->command(["deleteIndexes" => 'Element',"index" => "search_index"]);
        $locale = $this->dm->get('Configuration')->findConfiguration()->getLocale();
        if (in_array($locale, ["br", "eu"])) $locale = "none"; // Mongo does not support Breton nor Basque
        $db->selectCollection('Element')->createIndex($indexConf["fields"], [
            'name' => "search_index", 
            "default_language" => $locale,
            "weights" => $indexConf["weights"]
        ]);
    }

    private function calculateSearchIndexConfig($formFieldsJson) {
        $indexConf = [];
        $indexWeight = [];
        $formFields = json_decode($formFieldsJson);
        foreach ($formFields as $key => $field) {
            if (property_exists($field, 'search') && $field->search) {
                $path = "data.{$field->name}";
                if ($field->name == 'name') $path = 'name';
                $indexConf[$path] = "text";
                $indexWeight[$path] = (int) $field->searchWeight;
            }
        }
        // default index on name
        if (count($indexConf) == 0) {
            $indexConf = ['name' => 'text'];
            $indexWeight = ['name' => 1];
        }
        return ['fields' => $indexConf, 'weights' => $indexWeight];
    }
}
