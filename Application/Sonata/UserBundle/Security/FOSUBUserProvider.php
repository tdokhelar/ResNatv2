<?php

namespace App\Application\Sonata\UserBundle\Security;

use HWI\Bundle\OAuthBundle\OAuth\Response\UserResponseInterface;
use HWI\Bundle\OAuthBundle\Security\Core\User\FOSUBUserProvider as BaseClass;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\PropertyAccess\PropertyAccess;
use FOS\UserBundle\Model\UserManagerInterface;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;
use Doctrine\ODM\MongoDB\DocumentManager;
use Symfony\Contracts\Translation\TranslatorInterface;

class FOSUBUserProvider extends BaseClass
{
    
    public function __construct(UserManagerInterface $userManager, array $properties, FlashBagInterface $flash, DocumentManager $dm, TranslatorInterface $t )
    {
        $this->userManager = $userManager;
        $this->properties = array_merge($this->properties, $properties);
        $this->accessor = PropertyAccess::createPropertyAccessor();
        $this->flash = $flash;
        $this->dm = $dm;
        $this->t = $t;
    }
    /**
     * {@inheritdoc}
     */
    public function connect(UserInterface $user, UserResponseInterface $response)
    {
        $property = $this->getProperty($response);
        $username = $response->getUsername();
        //on connect - get the access token and the user ID
        $service = $response->getResourceOwner()->getName();
        $setter = 'set'.ucfirst($service);
        $setter_id = $setter.'Id';
        $setter_token = $setter.'AccessToken';
        //we "disconnect" previously connected users
        if (null !== $previousUser = $this->userManager->findUserBy([$property => $username])) {
            $previousUser->$setter_id(null);
            $previousUser->$setter_token(null);
            $this->userManager->updateUser($previousUser);
        }
        //we connect current user
        $user->$setter_id($username);
        $user->$setter_token($response->getAccessToken());
        $this->userManager->updateUser($user);
    }

    /**
     * {@inheritdoc}
     */
    public function loadUserByOAuthUserResponse(UserResponseInterface $response)
    {
        $config = $this->dm->get('Configuration')->findConfiguration();
        $manuallyActivateNewUsers = $config->getUser()->getManuallyActivateNewUsers();
        $username = $response->getUsername();
        // Find using communsUid, or facebookUid etc...
        $user = $this->userManager->findUserBy([$this->getProperty($response) => $username]);
        // If not exist try find existing user with same email
        if (null === $user) {
            $user = $this->userManager->findUserByEmail($response->getEmail());
        }
        $service = $response->getResourceOwner()->getName();
        // when the user is registrating
        if (null === $user) {   
            // create new user here
            $user = $this->userManager->createUser();     
            $user->setUsername($response->getNickname());
            $user->setFirstName($response->getFirstName());
            $user->setLastName($response->getLastName());
            $user->setEmail($response->getEmail());
            $user->setPassword($username);
            $user->setEnabled(false);
            if (!$manuallyActivateNewUsers) {
                $user->setEnabled(true);
            }
        }
        // Update specific service info (facebookUid, facebookName ...)
        $setter = 'set'.ucfirst($service);
        $setter_id = $setter.'Uid';
        $setter_name = $setter.'Name';
        $setter_token = $setter.'Data';        
        $user->$setter_id($username);
        $user->$setter_name($response->getNickname());
        $user->$setter_token($response->getAccessToken());
        $this->userManager->updateUser($user);        
        
        // Adds flash message
        if ($user->getEnabled() === true) {
            $this->flash->add('success', $this->t->trans('login.authentication_success', ['%service%' => $service])); 
        } elseif ($manuallyActivateNewUsers) {
            $this->flash->add('success', $this->t->trans('action.registration.on_hold', ['%email%' => $response->getEmail()]));
        }

        return $user;
    }
}
