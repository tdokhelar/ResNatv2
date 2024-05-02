<?php

namespace App\Controller;

use FOS\UserBundle\Controller\RegistrationController as FosController;

use FOS\UserBundle\Event\FilterUserResponseEvent;
use FOS\UserBundle\Event\FormEvent;
use FOS\UserBundle\Event\GetResponseUserEvent;
use FOS\UserBundle\FOSUserEvents;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Doctrine\ODM\MongoDB\DocumentManager;
use Symfony\Component\Form\FormError;
use Geocoder\ProviderAggregator;
use App\Document\Coordinates;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class RegistrationController extends FosController
{
    private $eventDispatcher;
    private $formFactory;
    private $userManager;
    private $tokenStorage;
    private $session;

    public function __construct($eventDispatcher, $formFactory, $userManager, $tokenStorage, \Swift_Mailer $mailer,
                                DocumentManager $dm, ProviderAggregator $geocoder, TranslatorInterface $t, SessionInterface $session)
    {
        $this->eventDispatcher = $eventDispatcher;
        $this->formFactory = $formFactory;
        $this->userManager = $userManager;
        $this->tokenStorage = $tokenStorage;
        $this->mailer = $mailer;
        $this->dm = $dm;
        $this->geocoder = $geocoder->using('nominatim');
        $this->t = $t;
        $this->session = $session;
    }

    /**
     * @param Request $request
     *
     * @return Response
     */
    public function registerAction(Request $request)
    {
        $config = $this->dm->get('Configuration')->findConfiguration();
        if (!$config->getUser()->getEnableRegistration()) {
            $this->session->getFlashBag()->add('error', $this->t->trans('action.registration.unallowed'));
            return $this->redirectToRoute('gogo_directory');
        }

        $user = $this->userManager->createUser();

        $event = new GetResponseUserEvent($user, $request);
        $this->eventDispatcher->dispatch(FOSUserEvents::REGISTRATION_INITIALIZE, $event);

        if (null !== $event->getResponse()) {
            return $event->getResponse();
        }

        $form = $this->formFactory->createForm();
        $form->setData($user);
        $form->handleRequest($request);

        $confirmationEnabled = $config->getUser()->getSendConfirmationEmail();
        $manuallyActivateNewUsers = $config->getUser()->getManuallyActivateNewUsers();
        if ($form->isSubmitted()) {

            $user = $form->getData();

            // CUSTOM VALIDATIONS
            $locationSetToReceiveNewsletter = $user->getNewsletterFrequency() > 0 && !$user->getLocation();

            $geocodeError = false;
            if ($user->getLocation()) {
                try {
                    $geocoded = $this->geocoder->geocode($user->getLocation())
                                ->first()->getCoordinates();
                    $user->setGeo(new Coordinates($geocoded->getLatitude(), $geocoded->getLongitude()));
                } catch (\Exception $error) {
                    $geocodeError = true;
                }
            }

            // CHECK FORM IS VALID
            if ($form->isValid() && !$locationSetToReceiveNewsletter && !$geocodeError) {

                if ($confirmationEnabled) {
                    // SEND CONFIRM EMAIL
                    // the registration_success event is intercepted by FOS EmailConfirmationListener
                    $event = new FormEvent($form, $request);
                    $this->eventDispatcher->dispatch(FOSUserEvents::REGISTRATION_SUCCESS, $event);
                    $response = $event->getResponse();
                }
                else {
                    if (!$manuallyActivateNewUsers) {
                        // DIRECTLY LOG THE USER
                        $user->setEnabled(true);
                        $url = $this->generateUrl('fos_user_registration_confirmed');
                        $response = new RedirectResponse($url);
                    } else {
                        $url = $this->generateUrl('gogo_user_register_on_hold', ['email' => $user->getEmail()]);
                        $response = new RedirectResponse($url);
                    }
                }

                $this->userManager->updateUser($user);

                if (!$manuallyActivateNewUsers) {
                    $this->eventDispatcher->dispatch(FOSUserEvents::REGISTRATION_COMPLETED, new FilterUserResponseEvent($user, $request, $response));
                }

                return $response;
            } else {
                // VALIDATION ERROR
                if ($locationSetToReceiveNewsletter) {
                    $form->get('location')->addError(new FormError($this->t->trans('action.registration.need_address')));
                }
                if ($geocodeError) {
                    $form->get('location')->addError(new FormError($this->t->trans('action.registration.geo_error')));
                }
                $this->eventDispatcher->dispatch(FOSUserEvents::REGISTRATION_FAILURE, $event);
            }
        }

        return $this->render('@FOSUser/Registration/register.html.twig', array(
            'form' => $form->createView(),
        ));
    }
    
    /**
     * Receive the confirmation token from user email provider, login the user.
     *
     * @param Request $request
     * @param string  $token
     *
     * @return Response
     */
    public function confirmAction(Request $request, $token)
    {
        $config = $this->dm->get('Configuration')->findConfiguration();
        $manuallyActivateNewUsers = $config->getUser()->getManuallyActivateNewUsers();
        
        $userManager = $this->userManager;

        $user = $userManager->findUserByConfirmationToken($token);

        if (null === $user) {
            throw new NotFoundHttpException(sprintf('The user with confirmation token "%s" does not exist', $token));
        }

        $user->setConfirmationToken(null);

        if (!$manuallyActivateNewUsers) {
            $user->setEnabled(true);

            $event = new GetResponseUserEvent($user, $request);
            $this->eventDispatcher->dispatch(FOSUserEvents::REGISTRATION_CONFIRM, $event);
    
            $userManager->updateUser($user);
    
            if (null === $response = $event->getResponse()) {
                $url = $this->generateUrl('fos_user_registration_confirmed');
                $response = new RedirectResponse($url);
            }
    
            $this->eventDispatcher->dispatch(FOSUserEvents::REGISTRATION_CONFIRMED, new FilterUserResponseEvent($user, $request, $response));
    
        } else {
            $url = $this->generateUrl('gogo_user_register_on_hold', ['email' => $user->getEmail()]);
            $response = new RedirectResponse($url);
        }
        
        return $response;
    }
    
    public function registerOnHoldAction(Request $request)
    {
        if ($request->get('email')) {
            $this->addFlash('success', $this->t->trans('action.registration.on_hold', ['%email%' => $request->get('email')]));
        }
        $nbFlashes = count($this->session->getFlashBag()->keys());
        if ($nbFlashes===0) {
            return $this->redirectToRoute('gogo_homepage');
        }
        return $this->render('user/register-on-hold.html.twig', [
            'title' => $this->t->trans('action.registration.title'),
        ]);
    }
}
