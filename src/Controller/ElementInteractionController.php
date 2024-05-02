<?php

/**
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @copyright Copyright (c) 2016 Sebastian Castro - 90scastro@gmail.com
 * @license    MIT License
 * @Last Modified time: 2018-04-07 16:22:43
 */

namespace App\Controller;

use App\Document\UserInteractionReport;
use App\Services\ConfigurationService;
use App\Services\ElementActionService;
use App\Services\ElementVoteService;
use App\Services\MailService;
use App\Services\UrlService;
use Doctrine\ODM\MongoDB\DocumentManager;
use GuzzleHttp\Client;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController as Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\Translation\TranslatorInterface;

class ElementInteractionController extends Controller
{
    public function voteAction(Request $request, DocumentManager $dm, ConfigurationService $confService,
                               ElementVoteService $voteService, TranslatorInterface $t)
    {
        if (!$confService->isUserAllowed('vote', $request)) {
            return $this->returnResponse(false, $t->trans('action.element.vote.unallowed'));
        }

        // CHECK REQUEST IS VALID
        if (!$request->get('elementId') || null === $request->get('value')) {
            return $this->returnResponse(false, $t->trans('action.element.vote.uncomplete'));
        }

        $element = $dm->get('Element')->find($request->get('elementId'));

        $resultMessage = $voteService->voteForElement($element, $request->get('value'),
                                                      $request->get('comment'),
                                                      $request->get('userEmail'));

        return $this->returnResponse(true, $resultMessage, $element->getStatus());
    }

    public function reportErrorAction(Request $request, DocumentManager $dm, ConfigurationService $confService,
                                      TranslatorInterface $t, MailService $mailService, UrlService $urlService)
    {
        if (!$confService->isUserAllowed('report', $request)) {
            return $this->returnResponse(false, $t->trans('action.element.report.unallowed'));
        }

        // CHECK REQUEST IS VALID
        if (!$request->get('elementId') || null === $request->get('value') || !$request->get('userEmail')) {
            return $this->returnResponse(false, $t->trans('action.element.delete.uncomplete'));
        }

        $element = $dm->get('Element')->find($request->get('elementId'));
        if (!$element) return $this->returnResponse(false, $t->trans('action.element.not_found'));

        // If not report duplicate
        if ($request->get('value') != 4) {
            $response = null;
            if ($element->isAggregate()) {
                foreach($element->getAggregatedElements() as $aggregated) {
                    if ($aggregated->getIsExternal()) {
                        $response = $this->reportToExternalSource($aggregated, $request, $dm, $t, $urlService, $mailService);
                    }
                }
            } else if ($element->getIsExternal()) {
                $response = $this->reportToExternalSource($element, $request, $dm, $t, $urlService, $mailService);
            }
            if ($response) return $response;
        }
        $report = new UserInteractionReport();
        $report->setValue($request->get('value'));
        $report->updateUserInformation($this->container->get('security.token_storage'), $request->get('userEmail'));
        $comment = $request->get('comment');
        if ($comment) {
            $report->setComment($comment);
        }

        $element->addReport($report);

        $element->updateTimestamp();

        $dm->persist($element);
        $dm->flush();

        return $this->returnResponse(true, $t->trans('action.element.report.done'));
    }

    private function reportToExternalSource($element, $request, $dm, $t, $urlService, $mailService)
    {
        if (!$element->getIsExternal()) $this->returnResponse(false, "This item have not been imported");
        if (!$element->getOldId()) $this->returnResponse(false, "This item do not have the ID of the original item, so we cannot transfer the report. Please map originalId in the import");

        $baseUrl = $element->getSource()->getGoGoCartoBaseUrl();
        $contact = $element->getSource()->getContact();
        $isContactAnUrl = $contact && str_contains($contact, '://') !== false;
        $appUrl = $urlService->generateUrl('gogo_homepage');

        if ($baseUrl || $isContactAnUrl) {

            $comment = $t->trans('action.element.report.from_origin', ['appUrl' => $appUrl]);
            $comment .= ' ' . $request->get('comment');
            $formParams = [
                'elementId' => $element->getOldId(),
                'gogoId' => $element->getId(),
                'url' => $urlService->elementShowUrl($element->getId()),
                'elementName' => $element->getName(),
                'value' => $request->get('value'),
                'userEmail' => $request->get('userEmail'),
                'comment' => $comment
            ];

            $client = new Client();
            $success = true;
            $reasonPhrase = '';

            if ($isContactAnUrl) { 
                // Send report by webhook
                $url = $contact;
                try {  
                    $resWebhook = $client->post($url, ['form_params' => $formParams]);
                } catch (\Exception $e) {
                    return $this->returnResponse(false, 'Webhook: ' . $e->getMessage());
                }
                $success = $success && $resWebhook->getStatusCode() == 200;
                $reasonPhrase .= 'Webhook: ' . $resWebhook->getReasonPhrase();
            }

            if ($baseUrl) { 
                // Send report to the original gogocarto map
                $url = $baseUrl . $this->generateUrl('gogo_report_error_for_element');
                try {  
                    $resGogo = $client->post($url, ['form_params' => $formParams]);
                } catch (\Exception $e) {
                    return $this->returnResponse(false, 'Gogocarto: ' . $e->getMessage());
                }
                $success = $success && $resGogo->getStatusCode() == 200;
                $reasonPhrase .= $reasonPhrase == '' ? '' : ' - ';
                $reasonPhrase .= 'Gogocarto: ' . $resGogo->getReasonPhrase();
            }

            return $this->returnResponse($success, $reasonPhrase);

        } else if ($contact) {
            // Send report by email to the source
            $config = $dm->get('Configuration')->findConfiguration();
            $subject = $t->trans("action.element.report.email.subject", [ 
                'appname' => $config->getAppName()
            ]);
            $valueLabel = $t->trans('elements.fields.itemValues_choices.' . $request->get('value'), [], 'admin');
            $content = $t->trans("action.element.report.email.content", [
                'appName' => $config->getAppName(),
                'appUrl' => $appUrl,
                'userEmail' => $request->get('userEmail'),
                'elementName' => $element->getName(),
                'elementId' => $element->getOldId(),
                'valueLabel' => $valueLabel,
                'comment' => $request->get('comment'),
            ]);
            $mailService->sendMail($element->getSource()->getContact(), $subject, $content);
            return $this->returnResponse(true, $t->trans('action.element.report.done'));
        }
        return null;
    }

    public function deleteAction(Request $request, DocumentManager $dm, ConfigurationService $confService,
                                 ElementActionService $elementActionService,
                                 TranslatorInterface $t)
    {
        if (!$confService->isUserAllowed('delete', $request)) {
            return $this->returnResponse(false, $t->trans('action.element.delete.unallowed'));
        }

        // CHECK REQUEST IS VALID
        if (!$request->get('elementId')) {
            return $this->returnResponse(false, $t->trans('action.element.delete.uncomplete'));
        }

        $element = $dm->get('Element')->find($request->get('elementId'));
        $dm->persist($element);

        $elementActionService->delete($element, true, $request->get('message'));

        $dm->flush();

        return $this->returnResponse(true, $t->trans('action.element.delete.done'));
    }

    public function resolveReportsAction(Request $request, DocumentManager $dm,
                                         ConfigurationService $confService,
                                         ElementActionService $elementActionService,
                                         TranslatorInterface $t)
    {
        if (!$confService->isUserAllowed('directModeration', $request)) {
            return $this->returnResponse(false, $t->trans('action.element.resolveReports.unallowed'));
        }

        // CHECK REQUEST IS VALID
        if (!$request->get('elementId')) {
            return $this->returnResponse(false, $t->trans('action.element.resolveReports.uncomplete'));
        }

        $element = $dm->get('Element')->find($request->get('elementId'));

        $elementActionService->resolveReports($element, $request->get('comment'), true);

        $dm->persist($element);
        $dm->flush();

        return $this->returnResponse(true, $t->trans('action.element.resolveReports.done'));
    }

    public function sendMailAction(Request $request, DocumentManager $dm, ConfigurationService $confService,
                                   MailService $mailService, TranslatorInterface $t)
    {
        // CHECK REQUEST IS VALID
        if (!$request->get('elementId') || !$request->get('subject') || !$request->get('content') || !$request->get('userEmail')) {
            return $this->returnResponse(false, $t->trans('action.element.sendMail.uncomplete'));
        }

        $element = $dm->get('Element')->find($request->get('elementId'));

        $senderMail = $request->get('userEmail');

        $emailSubject = $t->trans('action.element.sendMail.emailSubject', ['appName' => $confService->getConfig()->getAppName()]);
        $emailContent = $t->trans('action.element.sendMail.emailContent', ['element' => $element->getName(),
                                                                             'sender' => $senderMail,
                                                                             'subject' => $request->get('subject'),
                                                                             'content' => $request->get('content') ]);
                                                                             $mailService->sendMail($element->getEmail(), $emailSubject, $emailContent);

        return $this->returnResponse(true, $t->trans('action.element.sendMail.done'));
    }

    public function sendEditLinkAction($elementId, DocumentManager $dm, MailService $mailService, TranslatorInterface $t, Request $request)
    {
        $config = $dm->get('Configuration')->findConfiguration();
        $element = $dm->get('Element')->find($elementId);
        $emailSubject = $t->trans('action.element.sendMail.emailSubject', ['appName' => $config->getAppName()]);
        $emailContent = $t->trans('action.element.sendEditLink.emailContent');
        $emailContent = $mailService->replaceMailsVariables($emailContent, $element, '', 'edit-link', null);

        $mailService->sendMail($element->getEmail(), $emailSubject, $emailContent);

        $this->addFlash('success', $t->trans('action.element.sendEditLink.done', ['%email%' => $element->getEmail()]));
        
        if ($request->headers->get('referer') && str_contains($request->headers->get('referer'), '/admin/app/element/')) {
            return $this->redirectToRoute('admin_app_element_showEdit', ['id' => $elementId]);
        } else {
            return $this->redirectToRoute('gogo_homepage');
        }
    }

    public function stampAction(Request $request, DocumentManager $dm, TranslatorInterface $t)
    {
        // CHECK REQUEST IS VALID
        if (!$request->get('stampId') || null === $request->get('value') || !$request->get('elementId')) {
            return $this->returnResponse(false, $t->trans('action.element.stamp.uncomplete'));
        }

        $element = $dm->get('Element')->find($request->get('elementId'));
        $stamp = $dm->get('Stamp')->find($request->get('stampId'));
        $user = $this->getUser();

        if (!$stamp) {
            return $this->returnResponse(false, $t->trans('action.element.stamp.do_no_exist'));
        } else if (!in_array($stamp, $user->getAllowedStamps()->toArray()) && !$stamp->getIsPublic()) {
            return $this->returnResponse(false, $t->trans('action.element.stamp.unallowed'));
        } else if ('true' == $request->get('value')) {
            if (!in_array($stamp, $element->getStamps()->toArray())) $element->addStamp($stamp);
        } else {
            $element->removeStamp($stamp);
        }

        $dm->persist($element);
        $dm->flush();

        return $this->returnResponse(true, $t->trans('action.element.stamp.done'), $element->getStampIds() );
    }
    
    public function subscribeAction(Request $request, DocumentManager $dm, UrlService $urlService,
                                   MailService $mailService, TranslatorInterface $t)
    {
        // CHECK REQUEST IS VALID
        if (!$request->get('elementId') || !$request->get('userEmail')) {
            return $this->returnResponse(false, $t->trans('action.element.subscribe.uncomplete'));
        }

        $element = $dm->get('Element')->find($request->get('elementId'));
        $subscriberEmails = $element->getSubscriberEmails();
        $newSubscriberEmail = $request->get('userEmail');

        if (is_array($subscriberEmails) && in_array($newSubscriberEmail, $subscriberEmails)) {
            return $this->returnResponse(
                true,
                $t->trans('action.element.subscribe.already', [
                    '%element%' => $element->getName(),
                    '%email%' => $newSubscriberEmail
                ])
            );
        }
        
        $element->addSubscriberEmail($newSubscriberEmail);
        $dm->flush();
        
        return $this->returnResponse(
            true,
            $t->trans('action.element.subscribe.done', ['%element%' => $element->getName()])
        );
    }
    
    public function unsubscribeAction(Request $request, DocumentManager $dm, MailService $mailService, TranslatorInterface $t)
    {
        // CHECK REQUEST IS VALID
        if (!$request->get('elementId') || !$request->get('userEmail')) {
            return $this->returnResponse(false, $t->trans('action.element.unsubscribe.uncomplete'));
        }

        $element = $dm->get('Element')->find($request->get('elementId'));
        $subscriberEmails = $element->getSubscriberEmails();
        $subscriberEmailToRemove = $request->get('userEmail');

        if (! is_array($subscriberEmails) || ! in_array($subscriberEmailToRemove, $subscriberEmails)) {
            return $this->returnResponse(
                true,
                $t->trans('action.element.unsubscribe.already', [
                    '%element%' => $element->getName(),
                    '%email%' => $subscriberEmailToRemove
                ]),
                null,
                $request->get('format')
            );
        }
        $element->removeSubscriberEmail($subscriberEmailToRemove);
        $dm->flush();

        return $this->returnResponse(
            true,
            $t->trans('action.element.unsubscribe.done', ['%element%' => $element->getName()]),
            null,
            $request->get('format')
        );
    }
    

    private function returnResponse($success, $message, $data = null, $format=null)
    {
        $response['success'] = $success;
        $response['message'] = $message;
        if (null !== $data) {
            $response['data'] = $data;
        }
        
        if ($format === 'html') {
            $response = new Response('<html><body>' . $message . '</body></html>');
            $response->headers->set('Content-Type', 'text/html');
        } else {
            $responseJson = json_encode($response);
            $response = new Response($responseJson);
            $response->headers->set('Content-Type', 'application/json');
        }
        $response->headers->set('Access-Control-Allow-Origin', '*');

        return $response;
    }
}
