<?php

namespace App\Controller;

use App\Document\Coordinates;
use App\Document\ElementStatus;
use App\Document\ModerationState;
use App\Services\MailService;
use Doctrine\ODM\MongoDB\DocumentManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController as Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\Translation\TranslatorInterface;

class MailTestController extends Controller
{
    public function __construct(DocumentManager $dm, MailService $mailService, TranslatorInterface $t)
    {
        $this->dm = $dm;
        $this->mailService = $mailService;
        $this->t = $t;
    }

    private function trans($key, $params = [])
    {
        return $this->t->trans($key, $params, 'admin');
    }

    public function draftAutomatedAction($mailType)
    {
        $draftResponse = $this->draftTest($mailType);

        if (null == $draftResponse) {
            return new Response($this->trans('emails.test.uncomplete'));
        }

        if ($draftResponse['success']) {
            $mailContent = $this->mailService->draftTemplate($draftResponse['content']);

            return $this->render('emails/test-emails.html.twig', ['subject' => $draftResponse['subject'], 'content' => $mailContent, 'mailType' => $mailType]);
        } else {
            $this->addFlash('error', 'Error : '.$draftResponse['message']);

            return $this->redirectToRoute('admin_app_configuration_list');
        }
    }

    public function sendTestAction(Request $request, $mailType)
    {
        $mail = $request->get('email');

        if (!$mail) {
            return new Response($this->trans('emails.test.missing_email'));
        }

        if ('bulk-elements' === $mailType) {
            $elementId = $request->get('elementId');
            $element = $this->dm->get('Element')->find($elementId);
            if (!$element) {
                return new Response($this->trans('emails.test.element_not_found'));
                return $this->redirectToRoute('admin_app_configuration_list');
            }
            $subject = $request->get('subject');
            $content = $request->get('content');
            $draftResponse = $this->mailService->draftEmail($mailType, $element, '', null, $subject, $content);
        } else {
            $draftResponse = $this->draftTest($mailType, $request->get('elementId'));
        }

        if (null == $draftResponse) {
            $this->addFlash('error', $this->trans('emails.test.missing_element'));
            return $this->redirectToRoute('admin_app_configuration_list');
        }

        if ($draftResponse['success']) {
            $result = $this->mailService->sendMail($mail, $draftResponse['subject'], $draftResponse['content']);
            if ($result['success']) {
                $this->addFlash('success', $this->trans('emails.test.done', ['%mail%' => $mail]));
            } else {
                $this->addFlash('error', $result['message']);
            }
        } else {
            $this->addFlash('error', $this->trans('action.error', ['%message%' => $draftResponse['message']] ));
        }

        if ('bulk-elements' === $mailType) {
            return $this->redirectToRoute('gogo_mail_draft', [
                'mailSubject' => $subject,
                'mailContent' => $content,
                'elementId' => $elementId
            ]);
        } else {
            return $this->redirectToRoute('gogo_mail_draft_automated', ['mailType' => $mailType]);
        }
    }
    
    public function draftAction(Request $request)
    {
        $mailSubject = $request->get('mailSubject');
        $mailContent = $request->get('mailContent');
        $elementId = $request->get('elementId');
        $element = $this->dm->get('Element')->find($elementId);
        if (!$element) {
            $this->addFlash('error', $this->trans('emails.test.element_not_found'));
            return $this->redirectToRoute('admin_app_configuration_list');
        }
        $subject = $this->mailService->replaceMailsVariables($mailSubject, $element, '', 'bulk-elements', null);
        $content = $this->mailService->replaceMailsVariables($mailContent, $element, '', 'bulk-elements', null);
        $content = $this->mailService->draftTemplate($content);
        
        return $this->render('emails/test-emails.html.twig', [
            'mailType' => 'bulk-elements',
            'subject' => $subject,
            'content' => $content,
            'elementId' => $elementId,
        ]);
    }

    private function draftTest($mailType)
    {
        $options = null;

        if ('newsletter' == $mailType) {
            $element = $this->dm->get('User')->findOneByEnabled(true);
            $element->setLocation('bordeaux');
            $element->setGeo(new Coordinates(44.876, -0.512));
            $qb = $this->dm->query('Element');
            $qb->field('status')->gte(ElementStatus::AdminRefused);
            $qb->field('moderationState')->notIn([ModerationState::GeolocError, ModerationState::NoOptionProvided]);
            $options = $qb->limit(30)->execute();
        } else {
            $element = $this->dm->get('Element')->findVisibles()->getSingleResult();
        }

        if (!$element) {
            return null;
        }

        $draftResponse = $this->mailService->draftEmail($mailType, $element, $this->trans('emails.test.custom_message_example'), $options); 

        return $draftResponse;
    }
}
