<?php

namespace App\Services;

use App\Document\OptionValue;
use Doctrine\ODM\MongoDB\DocumentManager;
use App\Enum\UserInteractionType;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

class ElementFormService
{
    /**
     * Constructor.
     */
    private $elementActionService;
    private $pendingService;
    private $dm;
    private $interactionService;

    public function __construct(ElementActionService $elementActionService, 
                                ElementPendingService $pendingService, DocumentManager $dm,
                                ElementSynchronizationService $synchService,
                                UserInteractionService $interactionService,
                                SessionInterface $session)
    {
        $this->elementActionService = $elementActionService;
        $this->pendingService = $pendingService;
        $this->dm = $dm;
        $this->interactionService = $interactionService;
        $this->session = $session;
        $this->synchService = $synchService;
    }

    // Update element with no standard form attributes (option values, custom data...)
    public function handleFormSubmission($element, $request, $userEmail)
    {
        $request = $request->request;
        $this->updateOptionsValues($element, $request);
        $this->updateCustomData($element, $request);
        $this->updateOwner($element, $request, $userEmail);
        return $element;
    }

    public function checkDuplicates($element)
    {
        // check for duplicates
        $duplicates = $this->dm->get('Element')->findDuplicatesFor($element);
        $osmDuplicates = $this->synchService->checkIfNewElementShouldBeAddedToOsm($element)['duplicates'];
        if (count($osmDuplicates)) {
            // the duplicates returnes by OSM might already exist in gogocarto, so we
            // intersect osmduplicates with gogo duplicates
            $osmDuplicatesIdsInGoGoDatabase = [];
            foreach($duplicates as $duplicate) {
                if ($duplicate->isFromOsm()) $osmDuplicatesIdsInGoGoDatabase[] = $duplicate->getOldId();
            }
            foreach($osmDuplicates as $osmDuplicate) {
                $id = explode('/', $osmDuplicate['osmId'])[1]; // osmId is something like node/12345
                if (!in_array($id, $osmDuplicatesIdsInGoGoDatabase))
                    $duplicates[] = $osmDuplicate;
            }
        }
        if (count($duplicates) > 0) {
            $this->dm->flush(); // element source might have been modified by checkIfNewElementShouldBeAddedToOsm
            // saving values in session
            $this->session->set('duplicatesElements', $duplicates);
            return true;
        }
        return false;
    }

    // when we check for duplicates, we jump to an other action, and come back to this action
    public function handlesBackFromCheckDuplicates()
    {
        $element = $this->dm->get('Element')->find($this->session->get('pendingElementDuplicate'));

        // clear session
        $this->session->remove('pendingElementDuplicate');
        $this->session->remove('duplicatesElements');
        $this->session->remove('duplicatesFormAdmin');
        $this->session->remove('redirectToIfNoDuplicate');

        return $element;
    }

    public function save($element, $originalElement, $request, $isAllowedDirectModeration)
    {
        $sendMail = $request ? $request->request->get('send_mail') : false;
        $message = $request ? $request->request->get('admin-message') : null;
        
        // in case user edit it's own contribution, the element is still pending, and
        // we want to make it pending again. So we delete previous contribution and create a new one
        if ($originalElement->isPending() && $currContrib = $originalElement->getCurrContribution()) {
            $originalElement->removeContribution($currContrib);
            $this->dm->remove($currContrib);
        }       

        // back from checkduplicate pending element have an ID but no status
        if ($element->getId() && $element->getStatus() && !$originalElement->isPendingAdd()) {
            // Edit
            if ($isAllowedDirectModeration || $this->isMinorModification($element, $originalElement)) {
                $this->elementActionService->edit($element, $originalElement, $sendMail, $message);
            } else {
                $contrib = $this->interactionService->createContribution($element, $message, UserInteractionType::PendingEdit);
                $element = $this->pendingService->savePendingModification($element, $originalElement, $contrib);
            }
        } else {
            // Add
            if ($isAllowedDirectModeration) {
                $this->elementActionService->add($element, $message);
            } else {
                $this->interactionService->createContribution($element, $message, UserInteractionType::PendingAdd);
                $this->pendingService->createPending($element);
            }
        }
        
        return $element;
    }

    // Wait for the message to be persisted
    public function afterAdd($element, $request)
    {
        // Do not send for pending elements
        if ($element->getStatus() > 0) {
            $sendMail = $request ? $request->request->get('send_mail') : false;
            $message = $request ? $request->request->get('admin-message') : null;
            $this->elementActionService->afterAdd($element, $sendMail, $message);
        }
    }

    // when user only make a minor modification, we don't want to go through moderation
    // Here is a function to detect minor changes
    private function isMinorModification($element, $originalElement)
    {
        $changeset = $element->getChangeSet($this->dm, $originalElement);
        $nonImportantAttributes = ['geo', 'openHours'];
        foreach ($changeset as $attribute => $values) {
            if (in_array($attribute, $nonImportantAttributes) 
                || strpos($attribute, 'Json') !== false
                || startsWith($attribute, 'osm_')) {
                unset($changeset[$attribute]);
            }
        }
        return 0 == count($changeset);
    }

    public function updateOptionsValues($element, $request)
    {
        if (!$request->get('options-values')) return;

        $optionValues = [];

        // OptionValues from the form
        foreach($request->get('options-values') as $optionValuesString)
            $optionValues = array_merge($optionValues, json_decode($optionValuesString, true));

        // OptionValues from rootCategories not asked in the form, we preserve them
        $rootCategoriesIds = $this->dm->query('Category')->field('isRootCategory')->equals(true)->getIds();
        $rootCategoriesIdsSubmitted = array_keys($request->get('options-values'));
        $rootCategoriesIdsNotSubmitted = array_diff($rootCategoriesIds, $rootCategoriesIdsSubmitted);
        $optionsIdsToKeep = [];
        foreach($rootCategoriesIdsNotSubmitted as $catId) {
            $childOptionsIds = $this->dm->get('Category')->find($catId)->getAllOptionsIds();
            $optionsIdsToKeep = array_merge($optionsIdsToKeep, $childOptionsIds);
        };
        $existingOptionValuesToKeep = [];
        foreach($element->getOptionValues() as $optionValue) {
            if (in_array($optionValue->getOptionId(), $optionsIdsToKeep))
                $existingOptionValuesToKeep[] = $optionValue;
        }

        $element->resetOptionsValues();
        
        foreach ($optionValues as $optionValue) {
            if (! $this->isOrphanOptionValue($optionValue, $optionValues)) {
                $new_optionValue = new OptionValue();
                $new_optionValue->setOptionId($optionValue['optionId']);
                $new_optionValue->setIndex($optionValue['index']);
                $new_optionValue->setDescription($optionValue['description']);
                $element->addOptionValue($new_optionValue);
            }
        }

        foreach($existingOptionValuesToKeep as $optionValue) {
            $element->addOptionValue($optionValue);
        }
    }
    
    private function isOrphanOptionValue($optionValue, $optionValues) {
        $option = $this->dm->get('Option')->find($optionValue['optionId']);
        if ($option) {
            $parent = $option->getParentOption();
            if ($parent) {
                foreach($optionValues as $ov) {
                    if ($ov['optionId'] == $parent->getId()) {
                        return $this->isOrphanOptionValue($ov, $optionValues);
                    }
                }
            } else {
                return false;
            }
        }
        return true;
    }

    private function updateCustomData($element, $request)
    {
        $data = $request->get('data');
        if (!$data) $data = [];
        // For some fields, like elements type, we store the data stringified in a data-json input
        if ($request->get('data-json')) {
            foreach ($request->get('data-json') as $key => $value) {
                $data[$key] = json_decode($value);
            }
        }
        $element->setCustomData($data);
    }

    private function updateOwner($element, $request, $userEmail)
    {
        $config = $this->dm->get('Configuration')->findConfiguration();
        if ($config->getElementFormOwningText() && $userEmail &&
            (!$element->getUserOwnerEmail() || $element->getUserOwnerEmail() == $userEmail)) {
            if ($request->get('owning')) {
                $element->setUserOwnerEmail($userEmail);
            } else {
                $element->setUserOwnerEmail(null);
            }
        }
    }
}
