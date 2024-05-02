<?php

namespace App\Controller\Admin;

use App\Document\ElementStatus;
use App\Document\ModerationState;
use App\Document\OptionValue;
use App\Enum\UserInteractionType;
use App\EventListener\ElementJsonGenerator;
use App\Services\AsyncService;
use App\Services\ElementActionService;
use App\Services\ElementFormService;
use App\Services\MailService;
use App\Services\UserInteractionService;
use Doctrine\ODM\MongoDB\DocumentManager;
use Sonata\AdminBundle\Controller\CRUDController as Controller;
use Sonata\AdminBundle\Datagrid\ProxyQueryInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Security\Core\Security;

class ElementAdminBulkController extends Controller
{
    public function __construct(MailService $mailService, UserInteractionService $interactionService,
                                AsyncService $asyncService, ElementActionService $elementActionService,
                                ElementJsonGenerator $jsonGenerator, DocumentManager $dm,
                                ElementFormService $elementFormService,
                                Security $security)
    {
        $this->mailService = $mailService;
        $this->interactionService = $interactionService;
        $this->asyncService = $asyncService;
        $this->elementActionService = $elementActionService;
        $this->elementFormService = $elementFormService;
        $this->jsonGenerator = $jsonGenerator;
        $this->dm = $dm;
        $this->user = $security->getUser();
    }

    public function batchActionSoftDelete(ProxyQueryInterface $selectedModelQuery)
    {
        return $this->batchStatus($selectedModelQuery, 'softDelete');
    }

    public function batchActionRestore(ProxyQueryInterface $selectedModelQuery)
    {
        return $this->batchStatus($selectedModelQuery, 'restore');
    }

    public function batchActionResolveReports(ProxyQueryInterface $selectedModelQuery)
    {
        return $this->batchStatus($selectedModelQuery, 'resolveReports');
    }

    public function batchActionValidation(ProxyQueryInterface $selectedModelQuery)
    {
        return $this->batchStatus($selectedModelQuery, 'validation');
    }

    public function batchActionRefusal(ProxyQueryInterface $selectedModelQuery)
    {
        return $this->batchStatus($selectedModelQuery, 'refusal');
    }

    // BATCH STATUS (SOFT DELETE, RESTORE, RESOLVE, REFUSE, VALIDATE...)
    private function batchStatus(ProxyQueryInterface $selectedModelQuery, $actionName = '')
    {
        $this->admin->checkAccess('edit');

        $request = $this->get('request_stack')->getCurrentRequest()->request;

        $qb = clone $selectedModelQuery;
        $elementIds = $qb->getIds();
        $elementIdsString = '"'.implode(',', $elementIds).'"';
        $queryArray = $selectedModelQuery->getQuery()->getQuery()['query'];
        // if query is "get all elements", no need to specify all ids
        if ($queryArray == ['status' => ['$ne' => -5]]) {
            $elementIdsString = 'all';
        }

        $selectedModels = $selectedModelQuery->execute();
        $nbreModelsToProceed = $selectedModels->count();
        $isBulk = $nbreModelsToProceed > 2; // treat as bulk only if upper than X element to proceed

        $sendMail = !($request->has('dont-send-mail-'.$actionName) && $request->get('dont-send-mail-'.$actionName));
        $comment = $request->get('comment-'.$actionName);

        try {
            // BULK - PROCEED ALL ELEMENTS AT ONCE
            if ($isBulk && in_array($actionName, ['softDelete', 'restore', 'resolveReports'])) {
                // SEND EMAIL - ITERATE EACH ELEMENT WITHOUT DB OPERATIONS
                if ($nbreModelsToProceed < 5000) {
                    foreach ($selectedModels as $element) {
                        $mappingType = ['softDelete' => 'delete', 'restore' => 'add'];
                        if ($sendMail && isset($mappingType[$actionName])) {
                            $this->mailService->sendAutomatedMail($mappingType[$actionName], $element, $comment);
                        }
                        if ('resolveReports' == $actionName) {
                            foreach ($element->getUnresolvedReports() as $report) {
                                $this->mailService->sendAutomatedMail('report', $element, $comment, $report);
                            }
                        }
                        $element->setPreventJsonUpdate(true);
                    }
                } elseif ($sendMail || 'resolveReports' == $actionName) {
                    $this->addFlash('sonata_flash_error', $this->trans('emails.controller.error.not_sent'));
                }

                // CREATE CONTRIBUTION
                $contrib = null;
                switch ($actionName) {
                    case 'softDelete': $contrib = $this->interactionService->createContribution(null, $comment, UserInteractionType::Deleted, ElementStatus::Deleted); break;
                    case 'restore': $contrib = $this->interactionService->createContribution(null, $comment, UserInteractionType::Restored, ElementStatus::AddedByAdmin); break;
                    case 'resolveReports': $contrib = $this->interactionService->createContribution(null, $comment, UserInteractionType::ModerationResolved, ElementStatus::AdminValidate); break;
                }

                $contrib->setElementIds($elementIds);
                $this->dm->persist($contrib);

                // UPDATE EACH ELEMENT AT ONCE
                $mappingStatus = ['softDelete' => ElementStatus::Deleted, 'restore' => ElementStatus::AddedByAdmin];
                $qb = $selectedModelQuery->updateMany()
                    ->field('updatedAt')->set(new \DateTime());
                // Push contribution
                if ($contrib) {
                    $qb->field('contributions')->push($contrib);
                }
                // Update status
                if (isset($mappingStatus[$actionName])) {
                    $qb = $qb->field('status')->set($mappingStatus[$actionName])
                             ->field('moderationState')->set(ModerationState::NotNeeded);
                }
                // Reset Moderation
                if ('resolveReports' == $actionName) {
                    $qb = $qb->field('moderationState')->set(ModerationState::NotNeeded);
                }

                $qb->execute();

                // BATCH RESOLVE REPORTS
                if ('resolveReports' == $actionName) {
                    $this->dm->query('UserInteractionReport')
                       ->updateMany()
                       ->field('isResolved')->notEqual(true)
                       ->field('element.id')->in($elementIds)
                       ->field('isResolved')->set(true)
                       ->field('resolvedMessage')->set($comment)
                       ->field('resolvedBy')->set($this->getUser()->getEmail())
                       ->field('updatedAt')->set(new \DateTime())
                       ->execute();
                }

                $this->dm->flush();

                // update element json asyncronously
                $this->asyncService->callCommand('app:elements:updateJson', ['ids' => $elementIdsString]);
            }
            // PROCEED EACH ELEMENT ONE BY ONE
            else {
                $i = 0;
                foreach ($selectedModels as $selectedModel) {
                    switch ($actionName) {
                        case 'softDelete': $this->elementActionService->delete($selectedModel, $sendMail, $comment); break;
                        case 'restore': $this->elementActionService->restore($selectedModel, $sendMail, $comment); break;
                        case 'resolveReports': $this->elementActionService->resolveReports($selectedModel, $comment, true); break;
                        case 'validation': $this->elementActionService->resolve($selectedModel, true, 2, $comment); break;
                        case 'refusal': $this->elementActionService->resolve($selectedModel, false, 2, $comment); break;
                    }

                    if (0 == (++$i % 100)) {
                        $this->dm->flush();
                        $this->dm->clear();
                    }
                }
                $this->dm->flush();
                $this->dm->clear();
            }
        } catch (\Exception $e) {
            $this->addFlash('sonata_flash_error', $this->trans('emails.controller.error.error_occured', [ 'msg' => $this->escapeHtml($e->getMessage()) ]));

            return new RedirectResponse($this->admin->generateUrl('list', ['filter' => $this->admin->getFilterParameters()]));
        }

        $this->addFlash('sonata_flash_success', $this->trans('emails.controller.success.processed', [ 'nb' => $nbreModelsToProceed ]));
        // if ($nbreModelsToProceed >= $limit) $this->addFlash('sonata_flash_info', "Trop d'éléments à traiter ! Seulement " . $limit . " ont été traités");

        return new RedirectResponse($this->admin->generateUrl('list', ['filter' => $this->admin->getFilterParameters()]));
    }

    // BATCH HARD DELETE
    public function batchActionDelete(ProxyQueryInterface $selectedModelQuery)
    {
        // Add contribution for webhook - Get elements visible, no need to add a contribution if element where already soft deleted for example
        $selectedModels = clone $selectedModelQuery;
        $elementIds = $selectedModels->field('status')->gte(-1)->getIds();
        if (count($elementIds)) {
            $contribution = $this->interactionService->createContribution(null, null, UserInteractionType::Deleted, ElementStatus::Deleted);
            $contribution->setElementIds($elementIds);
            $this->dm->persist($contribution);
        }

        // Add element id to ignore to sources
        $selectedModels = clone $selectedModelQuery;
        $elements = $selectedModels
            ->select('oldId', 'source.$id')
            ->field('source.$id')->exists(true)
            ->field('oldId')->exists(true)
            ->getArray();
        $elementsIdsGroupedBySource = [];
        foreach($elements as $element) {
            if (isset($element['source']['$id']) && isset($element['oldId'])) {
                $elementsIdsGroupedBySource[$element['source']['$id']][] = $element['oldId'];
            }
        }
        foreach ($elementsIdsGroupedBySource as $sourceId => $elementIds) {
            usort($elementIds, 'strnatcasecmp');
            $qb = $this->dm->query('Import');
            $qb->updateOne()
               ->field('id')->equals($sourceId)
               ->field('idsToIgnore')->addToSet($qb->expr()->each($elementIds))
               ->execute();
        }

        // Perform remove
        $modelManager = $this->admin->getModelManager();

        try {
            $modelManager->batchDelete($this->admin->getClass(), $selectedModelQuery);
            $this->addFlash(
                'sonata_flash_success',
                $this->trans('flash_batch_delete_success', [], 'SonataAdminBundle')
            );
        } catch (\Sonata\AdminBundle\Exception\ModelManagerException $e) {
            $this->handleModelManagerException($e);
            $this->addFlash(
                'sonata_flash_error',
                $this->trans('flash_batch_delete_error', [], 'SonataAdminBundle')
            );
        }

        $this->dm->flush();

        return new RedirectResponse($this->admin->generateUrl('list', ['filter' => $this->admin->getFilterParameters()]));
    }

    // BATCH SEND EMAILS
    public function batchActionSendMail(ProxyQueryInterface $selectedModelQuery)
    {
        $this->get('session')->getFlashBag()->clear();
        $redirectResponse = new RedirectResponse($this->admin->generateUrl('list', $this->admin->getFilterParameters()));
        
        $request = $this->get('request_stack')->getCurrentRequest()->request;

        if (!$request->get('mail-subject') || !$request->get('mail-content')) {
            $this->addFlash('sonata_flash_error', $this->trans('emails.controller.error.missing_data'));
            return $redirectResponse;
        }
        
        $selectedModels = $selectedModelQuery->execute();
        $nbreModelsToProceed = $selectedModels->count();
        $selectedModels->limit(5000);

        if ($nbreModelsToProceed >= 5000) {
            $this->addFlash('sonata_flash_info', $this->trans('emails.controller.info.too_many', [ 'nb' => 5000 ]));
            return $redirectResponse;
        }
        
        $mails = [];
        $mailsSent = 0;
        $elementWithoutEmail = 0;

        try {
            foreach ($selectedModels as $element) {
                
                $mailSubject = $this->mailService->replaceMailsVariables($request->get('mail-subject'), $element, '', 'bulk-elements', null);
                $mailContent = $this->mailService->replaceMailsVariables($request->get('mail-content'), $element, '', 'bulk-elements', null);
                
                $mail = [];
                if ($request->get('send-to-element') && $element->getEmail()) {
                    $mail[] = $element->getEmail();
                }
                $mailContrib = [];
                if ($request->get('send-to-last-contributor')) {
                    $contrib = $element->getCurrContribution();
                    $mailContrib = $contrib ? $contrib->getUserEmail() : null;
                    if ('no email' == $mailContrib) {
                        $mailContrib = null;
                    }
                }
                $to = array_merge($mail, $mailContrib);
                
                if (!count($to)>0) {
                    ++$elementWithoutEmail;
                } else {
                    $mails[] = [
                        'to' => $to,
                        'mailSubject' => $mailSubject,
                        'mailContent' => $mailContent
                    ];
                }
            }
        } catch (\Exception $e) {
            $this->addFlash('sonata_flash_error', 'ERROR: '.$e->getMessage());
            return $redirectResponse;
        }

        foreach($mails as $mail) {
            $result = $this->mailService->sendMail(null, $mail['mailSubject'], $mail['mailContent'], $request->get('from'), $mail['to']);
            if ($result['success']) {
                ++$mailsSent;
            } else {
                $this->addFlash('sonata_flash_error', $result['message']);
            }
        }

        if ($mailsSent > 0) {
            $this->addFlash('sonata_flash_success', $this->trans('emails.controller.success.sent', [ 'nb' => $mailsSent ]));
        }

        if ($elementWithoutEmail > 0) {
            $this->addFlash('sonata_flash_error', $this->trans('emails.controller.error.no_email', [ 'nb' => $elementWithoutEmail ]));
        }

        return $redirectResponse;
    }

    // BATCH EDIT OPTIONS
    public function batchActionEditOptions(ProxyQueryInterface $selectedModelQuery)
    {
        $this->admin->checkAccess('edit');

        $request = $this->get('request_stack')->getCurrentRequest()->request;

        $selectedModels = $selectedModelQuery->execute();
        $nbreModelsToProceed = $selectedModels->count();

        $limit = 2000;
        $selectedModels->limit($limit);

        $optionstoRemoveIds = $request->get('optionsToRemove');
        $optionstoAddIds = $request->get('optionsToAdd');

        try {
            $i = 0;
            foreach ($selectedModels as $selectedModel) {
                $optionsValues = $selectedModel->getOptionValues()->toArray();
                if ($optionstoRemoveIds && count($optionstoRemoveIds) > 0) {
                    $optionsToRemove = $this->dm->query('Option')->field('id')->in($optionstoRemoveIds)
                                                       ->getArray();
                    $optionstoRemoveIds = array_map(function ($opt) { return $opt->getIdAndChildrenOptionIds(); }, $optionsToRemove);
                    $optionstoRemoveIds = array_unique($this->flatten($optionstoRemoveIds));

                    $optionValuesToBeRemoved = array_filter($optionsValues, function ($oV) use ($optionstoRemoveIds) { return in_array($oV->getOptionId(), $optionstoRemoveIds); });

                    foreach ($optionValuesToBeRemoved as $key => $optionValue) {
                        $selectedModel->removeOptionValue($optionValue);
                    }
                }

                if ($optionstoAddIds && count($optionstoAddIds) > 0) {
                    $optionsToAdd = $this->dm->query('Option')->field('id')->in($optionstoAddIds)->getArray();
                    $optionstoAddIds = array_map(function ($opt) { return $opt->getIdAndParentOptionIds(); }, $optionsToAdd);
                    $optionstoAddIds = array_unique($this->flatten($optionstoAddIds));

                    $optionValuesIds = array_map(function ($x) { return $x->getOptionId(); }, $optionsValues);

                    foreach ($optionstoAddIds as $key => $optionId) {
                        if (!in_array($optionId, $optionValuesIds)) {
                            $optionValue = new OptionValue();
                            $optionValue->setOptionId($optionId);
                            $selectedModel->addOptionValue($optionValue);
                        }
                    }
                }
                if (0 == (++$i % 100)) {
                    $this->dm->flush();
                    $this->dm->clear();
                }
            }
            $this->dm->flush();
            $this->dm->clear();
        } catch (\Exception $e) {
            $this->addFlash('sonata_flash_error', $this->trans('emails.controller.error.error_occured', [ 'msg' => $e->getMessage() ]));

            return new RedirectResponse($this->admin->generateUrl('list', ['filter' => $this->admin->getFilterParameters()]));
        }

        $this->addFlash('sonata_flash_success', $this->trans('emails.controller.success.categories_updated', [ 'nb' => min([$nbreModelsToProceed, $limit]) ]));
        if ($nbreModelsToProceed >= $limit) {
            $this->addFlash('sonata_flash_info', $this->trans('emails.controller.info.too_many', [ 'nb' => $limit ]));
        }

        return new RedirectResponse($this->admin->generateUrl('list', ['filter' => $this->admin->getFilterParameters()]));
    }

    // BATCH EDIT Data
    public function batchActionEditData(ProxyQueryInterface $selectedModelQuery)
    {
        $this->admin->checkAccess('edit');

        $request = $this->get('request_stack')->getCurrentRequest()->request;
        $field = $request->get('fieldName');
        $value = $request->get('fieldValue');
        $query = clone $selectedModelQuery;
        $elementIdsString = implode(',', $query->getIds());

        $selectedModelQuery->updateMany()
                           ->field("data.$field")->set($value)
                           ->execute();

        $this->asyncService->callCommand('app:elements:updateJson', ['ids' => $elementIdsString]);
        $this->addFlash('sonata_flash_success', 'Success');
        return new RedirectResponse($this->admin->generateUrl('list', ['filter' => $this->admin->getFilterParameters()]));    
    }

    private function flatten(array $array)
    {
        $return = [];
        array_walk_recursive($array, function ($a) use (&$return) { $return[] = $a; });

        return $return;
    }
}
