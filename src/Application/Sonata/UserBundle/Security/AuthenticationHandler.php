<?php

namespace App\Application\Sonata\UserBundle\Security;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Security\Http\Authentication\AuthenticationFailureHandlerInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface;
use Symfony\Component\Security\Http\Logout\LogoutSuccessHandlerInterface;

class AuthenticationHandler implements AuthenticationSuccessHandlerInterface, AuthenticationFailureHandlerInterface, LogoutSuccessHandlerInterface
{
    private $router;
    private $session;

    public function __construct(RouterInterface $router, SessionInterface $session,
                                                          TokenStorageInterface $securityToken)
    {
        $this->router = $router;
        $this->session = $session;
        $this->securityToken = $securityToken;
    }

    /**
     * onAuthenticationSuccess.
     *
     * @author 	Joe Sexton <joe@webtipblog.com>
     *
     * @return Response
     */
    public function onAuthenticationSuccess(Request $request, TokenInterface $token)
    {
        // if AJAX login
        if ($request->isXmlHttpRequest()) {
            $user = $this->securityToken->getToken()->getUser();
            $redirectionUrl = '';
            if ($this->session->get('_security.main.target_path')) {
                $redirectionUrl = $this->session->get('_security.main.target_path');
            }
            $array = ['success' => true,
                                 'redirectionUrl' => $redirectionUrl,
                                 'roles' => $user->getRoles(),
                                 'groups' => $user->getGroupNames(),
                                 'username' => $user->getUsername(),
                                 'email' => $user->getEmail(), ]; // data to return via JSON

            $response = new Response(json_encode($array));
            $response->headers->set('Content-Type', 'application/json');

            return $response;

        // if form login
        } else {
            if ($this->session->get('_security.main.target_path')) {
                $url = $this->session->get('_security.main.target_path');
            } else {
                $url = $this->router->generate('gogo_homepage');
            } // end if

            return new RedirectResponse($url);
        }
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception)
    {
        // if AJAX login
        if ($request->isXmlHttpRequest()) {
            $array = ['success' => false, 'message' => $exception->getMessage()]; // data to return via JSON
            $response = new Response(json_encode($array));
            $response->headers->set('Content-Type', 'application/json');

            return $response;

        // if form login
        } else {
            // set authentication exception to session
            $this->session->set(Security::AUTHENTICATION_ERROR, $exception);

            return new RedirectResponse($this->router->generate('login_route'));
        }
    }

    public function onLogoutSuccess(Request $request)
    {
        $this->session->remove('emailToCreateAccount');

        return new Response('{"success": true}');
    }
}
