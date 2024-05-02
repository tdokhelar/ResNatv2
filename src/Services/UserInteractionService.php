<?php

namespace App\Services;

use App\Document\ElementStatus;
use App\Document\UserInteractionContribution;
use App\Enum\UserInteractionType;
use App\Document\Webhook;
use App\Document\WebhookPost;
use Doctrine\ODM\MongoDB\DocumentManager;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Service used to handle to resolution of pending Elements.
 **/
class UserInteractionService
{
    /**
     * Constructor.
     */
    public function __construct(DocumentManager $dm, TokenStorageInterface $securityContext, 
                                TranslatorInterface $translator)
    {
        $this->dm = $dm;
        $this->securityContext = $securityContext;
        $this->translator = $translator;
    }

    private function t($key, $params = [])
    {
        return $this->translator->trans($key, $params, 'admin');
    }

    public function createContribution($element, $message, $userInteractionType, $status = null, $externalOperator=null)
    {
        $contribution = new UserInteractionContribution();
        $contribution->setType($userInteractionType);
        $contribution->updateUserInformation($this->securityContext);
        $contribution->setResolvedMessage($message);
        $contribution->setExternalOperator($externalOperator);

        // pending contribution does not have status
        if ($status) {
            $contribution->updateResolvedBy($this->securityContext);
            $contribution->setStatus($status);
        }

        // Create webhook posts to be dispatched
        if ($element) {
            if ($userInteractionType != UserInteractionType::ModerationResolved) {
                $webhooks = $this->dm->get('Webhook')->findAll();
                // do not sent webhook for elements not managed by this map (external sources)
                if (!$element->isExternalReadOnly())
                    foreach ($webhooks as $webhook) {
                        $this->createPostFor($contribution, $webhook);
                    }
                // do not create a post when the modif come from the import itself !
                if ($element->isSynchedWithExternalDatabase() && $element->getCurrentlyEditedBy() != 'import') {
                    $this->createPostFor($contribution, null);
                }
            }
            $element->addContribution($contribution);
        }
        return $contribution;
    }

    private function createPostFor($contribution, $webhook)
    {
        $post = new WebhookPost();
        if ($webhook) $post->setWebhook($webhook);
        $post->setNextAttemptAt(new \DateTime());
        $contribution->addWebhookPost($post);
    }

    public function resolveContribution($element, $isAccepted, $validationType, $message)
    {
        $contribution = $element->getCurrContribution();
        if (!$contribution) {
            return;
        }
        if (!$isAccepted) {
            $contribution->clearWebhookPosts();
        }

        if (2 == $validationType) { // 2 = ValidationType::Admin
            $contribution->setResolvedMessage($message);
            $contribution->updateResolvedby($this->securityContext);
            $contribution->setStatus($isAccepted ? ElementStatus::AdminValidate : ElementStatus::AdminRefused);
        } else {
            $text = $isAccepted ? $this->t('pending-contributions.approved') : $this->t('pending-contributions.rejected');
            $contribution->setResolvedMessage($text);
            $contribution->setResolvedby('Collaborative process');
            $contribution->setStatus($isAccepted ? ElementStatus::CollaborativeValidate : ElementStatus::CollaborativeRefused);
        }
    }
}
