<?php

namespace App\EventListener;

use App\Document\Configuration;
use App\Document\Coordinates;
use App\Document\Element;
use App\Document\OpenHours;
use App\Document\PostalAddress;
use App\Services\MailService;
use App\Services\UrlService;
use Doctrine\ODM\MongoDB\DocumentManager;
use Symfony\Contracts\Translation\TranslatorInterface;

class ElementUpdateSubscriptions
{
    protected $logger;
    protected $mailerService;

    public function __construct(
        DocumentManager $dm,
        MailService $mailService,
        UrlService $urlService,
        TranslatorInterface $t
    ) {
        $this->dm = $dm;
        $this->t = $t;
        $this->mailService = $mailService;
        $this->urlService = $urlService;
        $this->config = $this->dm->get('Configuration')->findConfiguration();
        if ($this->config) {
            $this->subscriptionProperties = $this->config->getSubscription()->getSubscriptionProperties();
        }
        $this->sent = false;
    }

    public function onFlush(\Doctrine\ODM\MongoDB\Event\OnFlushEventArgs $eventArgs): void
    {

        if (!$this->config) {
            return;
        }

        $uow = $this->dm->getUnitOfWork();

        foreach ($uow->getScheduledDocumentUpdates() as $document) {

            if ($this->config->getSubscribeFeature()->getActive()) {
                $changeSet = $uow->getDocumentChangeSet($document);
                if ($document instanceof Configuration) {
                    if (array_key_exists('subscriptionMail', $changeSet)) {
                        $this->config->getSubscriptionMail()->setActive(true);
                    }
                }

                if (!$this->sent) {

                    if ($document instanceof OpenHours) {
                        $element = $uow->getParentAssociation($document)[1];
                        if (in_array('openHours', $this->subscriptionProperties)) {
                            $this->sendSubscriptionEMail($element);
                        }
                    }

                    if ($document instanceof Coordinates) {
                        $element = $uow->getParentAssociation($document)[1];
                        if (in_array('geo', $this->subscriptionProperties)) {
                            $this->sendSubscriptionEMail($element);
                        }
                    }

                    if ($document instanceof PostalAddress) {
                        $element = $uow->getParentAssociation($document)[1];
                        if (in_array('address', $this->subscriptionProperties)) {
                            $this->sendSubscriptionEMail($element);
                        }
                    }

                    if ($document instanceof Element) {
                        $element = $document;
                        // Check for sending email to subscribers
                        if (!$this->isMinorModificationForSubscriptions($changeSet)) {
                            $subscriberEmails = $element->getSubscriberEmails();
                            if (is_array($subscriberEmails) && count($subscriberEmails) > 0) {
                                $this->sendSubscriptionEMail($element);
                            }
                        }
                    }
                }

                if ($document instanceof Element) {

                    // Send mail with unsubscribe link for new subscribers
                    if (array_key_exists('subscriberEmails', $changeSet)) {

                        if (!$changeSet['subscriberEmails'][0]) {
                            $newSubscriberEmails = $changeSet['subscriberEmails'][1];
                        } else {
                            $newSubscriberEmails = array_diff($changeSet['subscriberEmails'][1], $changeSet['subscriberEmails'][0]);
                        }

                        $appUrl = $this->urlService->generateUrl('gogo_homepage');

                        foreach ($newSubscriberEmails as $newSubscriberEmail) {

                            $unsubscribeUniqueUrl =  $this->urlService->generateUrl(
                                'gogo_element_unsubscribe',
                                [
                                    'elementId' => $element->getId(),
                                    'userEmail' => $newSubscriberEmail,
                                    'format' => 'html'
                                ]
                            );

                            $subject = $this->t->trans("action.element.subscribe.email.subject", [
                                'appName' => $this->config->getAppName(),
                                'elementName' => $element->getName()
                            ]);
                            $content = $this->t->trans("action.element.subscribe.email.content", [
                                'appName' => $this->config->getAppName(),
                                'appUrl' => $appUrl,
                                'elementName' => $element->getName(),
                                'unsubscribeUniqueUrl' => $unsubscribeUniqueUrl
                            ]);
                            $this->mailService->sendMail($newSubscriberEmail, $subject, $content);
                        }
                    }
                }
            }
        }
    }

    private function sendSubscriptionEMail($element)
    {
        $this->mailService->sendAutomatedMail('subscription', $element);
        $this->sent = true;
    }

    private function isMinorModificationForSubscriptions($changeSet)
    {

        if (count($this->subscriptionProperties) === 0) {
            return false;
        }

        foreach ($changeSet as $key => $value) {
            if (in_array($key, ['address', 'optionsString', 'geo', 'openHours'])) {
                $oldValue = $changeSet[$key][0];
                $newValue = $changeSet[$key][1];
                if ($oldValue !=  $newValue && in_array($key, $this->subscriptionProperties)) {
                    return false;
                }
            }
        }

        if (!array_key_exists('data', $changeSet) || !$changeSet['data'][1]) {
            return true;
        }

        foreach ($changeSet['data'][1] as $key => $value) {
            $oldValue = null;
            if ($changeSet['data'][0] && array_key_exists($key, $changeSet['data'][0])) {
                $oldValue = $changeSet['data'][0][$key];
            }
            $newValue = $value;
            if ($oldValue !==  $newValue && in_array($key, $this->subscriptionProperties)) {
                return false;
            }
        }

        return true;
    }
}
