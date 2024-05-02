<?php

namespace App\Services;

use App\Document\ElementStatus;
use App\Document\ModerationState;
use App\Enum\UserInteractionType;
use Doctrine\ODM\MongoDB\DocumentManager;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * Service used to handle to resolution of pending Elements.
 **/
class ElementActionService
{
    /**
     * Constructor.
     */
    public function __construct(DocumentManager $dm, TokenStorageInterface $securityContext, 
                                MailService $mailService, ElementPendingService $elementPendingService, 
                                UserInteractionService $interactionService)
    {
        $this->dm = $dm;
        $this->securityContext = $securityContext;
        $this->mailService = $mailService;
        $this->elementPendingService = $elementPendingService;
        $this->interactionService = $interactionService;
    }

    public function add($element, $message = null)
    {
        $this->addContribution($element, $message, UserInteractionType::Add, ElementStatus::AddedByAdmin);
        $element->setStatus(ElementStatus::AddedByAdmin);
        $element->updateTimestamp();
    }

    public function afterAdd($element, $sendMail = true, $message = null)
    {
        if ($sendMail) {
            // Needs the element to be persisted in order to generate proper url inside the email
            $this->mailService->sendAutomatedMail('add', $element, $message);
        }
    }

    public function edit(
        $element,
        $originalElement,
        $sendMail = true,
        $message = null,
        $keepStatus = false,
        $externalOperator = null
    ){
        $changeset = $element->getChangeset($this->dm, $originalElement);
        if (count($changeset) == 0) return;

        if (ElementStatus::ModifiedPendingVersion == $element->getStatus()) {
            $element = $this->dm->get('Element')->findOriginalElementOfModifiedPendingVersion($element);
            $this->resolve($element, true, ValidationType::Admin, $message);
        } elseif ($sendMail) {
            $this->mailService->sendAutomatedMail('edit', $element, $message);
        }
        $status = $element->getStatus();
        if (!$keepStatus) {
            $status = ElementStatus::ModifiedByAdmin;
            if (isset($_GET['hash'])) $status = ElementStatus::ModifiedFromHash;
        }
        $contrib = $this->addContribution($element, $message, UserInteractionType::Edit, $status, $externalOperator);
        $contrib->setChangeset($changeset);
        if (!$keepStatus) {
            $element->setStatus($status);
        }
        $element->updateTimestamp();        
    }

    public function resolve($element, $isAccepted, $validationType = ValidationType::Admin, $message = null)
    {
        $this->elementPendingService->resolve($element, $isAccepted, $validationType, $message);
        $element->updateTimestamp();
    }

    public function delete($element, $sendMail = true, $message = null)
    {
        if ($sendMail) {
            $this->mailService->sendAutomatedMail('delete', $element, $message);
        }
        // do not add contribution for elements already deleted
        if ($element->isVisible()) {
            $this->addContribution($element, $message, UserInteractionType::Deleted, ElementStatus::Deleted);
        }

        $newStatus = $element->isPotentialDuplicate() ? ElementStatus::Duplicate : ElementStatus::Deleted;
        $element->setStatus($newStatus);
        $this->resolveReports($element, $message);
        $element->updateTimestamp();
    }

    public function restore($element, $sendMail = true, $message = null)
    {
        $this->addContribution($element, $message, UserInteractionType::Restored, ElementStatus::AddedByAdmin);
        $element->setStatus(ElementStatus::AddedByAdmin);
        $this->resolveReports($element, $message);
        if ($sendMail) {
            $this->mailService->sendAutomatedMail('add', $element, $message);
        }
        $element->setDuplicateOf(null); // reset this field
        $element->updateTimestamp();
    }

    public function resolveReports($element, $message = '', $addContribution = false)
    {
        $reports = $element->getUnresolvedReports();
        if (count($reports) > 0) {
            foreach ($reports as $key => $report) {
                $report->setResolvedMessage($message);
                $report->updateResolvedBy($this->securityContext);
                $report->setIsResolved(true);
                $this->mailService->sendAutomatedMail('report', $element, $message, $report);
            }
        } elseif ($addContribution) {
            $this->addContribution($element, $message, UserInteractionType::ModerationResolved, $element->getStatus());
        }

        $element->updateTimestamp();
        $element->setModerationState(ModerationState::NotNeeded);
    }

    private function addContribution($element, $message, $userInteractionType, $status, $externalOperator=null)
    {
        return $this->interactionService->createContribution($element, $message, $userInteractionType, $status, $externalOperator);
    }
}
