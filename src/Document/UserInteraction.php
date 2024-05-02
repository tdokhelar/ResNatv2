<?php

namespace App\Document;

use Doctrine\ODM\MongoDB\Mapping\Annotations as MongoDB;
use App\Enum\UserInteractionType;
use Gedmo\Mapping\Annotation as Gedmo;

final class UserRoles
{
    const Anonymous = 0;
    const Loggued = 2;
    const Admin = 3;
    const AuthenticatedWithHash = 4;
    const GoGoBot = 5;
}

/** @MongoDB\Document */
class UserInteraction
{
    /** @MongoDB\Id(strategy="ALNUM") */
    protected $id;

    /**
     * @var int
     *
     * @MongoDB\Field(type="int")
     * @MongoDB\Index
     */
    protected $type;

    /**
     * @var string
     *
     * @MongoDB\Field(type="string")
     * @MongoDB\Index
     */
    protected $userRole = 0;

    /**
     * @var string
     *
     * UserEmail
     *
     * @MongoDB\Field(type="string")
     */
    protected $userEmail = 'no email';

    /**
     * @var \stdClass
     *
     * Elements related to this interaction
     * @MongoDB\Index
     * @MongoDB\ReferenceOne(targetDocument="App\Document\Element")
     */
    protected $element;

    /**
     * @var string
     *
     * User who resolved the contribution (can also be a collaborative resolved)
     *
     * @MongoDB\Field(type="string")
     */
    private $resolvedBy;

    /**
     * @var string
     *
     * Message filled by the admin when resolving the contribution (explaination about delete, edit etc...)
     *
     * @MongoDB\Field(type="string")
     */
    private $resolvedMessage;

    /**
     * @var date
     *
     * @MongoDB\Field(type="date") @MongoDB\Index
     * @Gedmo\Timestampable(on="create")
     */
    protected $createdAt;

    /**
     * @var date
     *
     * @MongoDB\Field(type="date") @MongoDB\Index
     */
    protected $updatedAt;

    /**
     * @MongoDB\EmbedMany(targetDocument="App\Document\WebhookPost")
     */
    protected $webhookPosts;

    public function getTimestamp()
    {
        $date = $this->createdAt;
        if ($this->updatedAt && in_array($this->type, [UserInteractionType::Report, UserInteractionType::Vote]))
            $date = $this->updatedAt;
        
        return null == $date ? 0 : $date->getTimestamp();
    }

    public function updateTimestamp()
    {
        $this->setUpdatedAt(new \DateTime());
    }

    public function isDirectModeration()
    {
        return !in_array($this->getType(), [UserInteractionType::PendingAdd, UserInteractionType::PendingEdit]);
    }

    public function updateUserInformation($securityContext, $nonLoggedEmail = null)
    {
        $user = $securityContext->getToken() ? $securityContext->getToken()->getUser() : null;
        if ($user == "GoGoBot") {
            $this->setUserRole(UserRoles::GoGoBot);
        } else if ($user && is_object($user)) {
            $this->setUserEmail($user->getEmail());
            $this->setUserRole($user->isAdmin() ? UserRoles::Admin : UserRoles::Loggued);
        } else if ($nonLoggedEmail) {
            $this->setUserEmail($nonLoggedEmail);  
        } else {
            if (isset($_GET['hash'])) $this->setUserRole(UserRoles::AuthenticatedWithHash);
            else $this->setUserRole(UserRoles::Anonymous);
        }
    }

    public function updateResolvedBy($securityContext, $automatic = false)
    {
        $user = $securityContext->getToken() ? $securityContext->getToken()->getUser() : null;
        $user = is_object($user) ? $user : null;
        if ($automatic) {
            $this->setResolvedBy('GoGoBot');
        } else if ($user) {
            $this->setResolvedBy($user->getEmail());
        } else {
            if (isset($_GET['hash'])) $this->setResolvedBy('Anonymous with hash');
            else $this->setResolvedBy('Anonymous');
        }
        $this->updateTimestamp();
    }

    public function isMadeBy($user, $userEmail = null)
    {
        if ($user == "GoGoBot") {
            return $this->getUserRole() == UserRoles::GoGoBot;
        } else if (is_object($user)) {
            return $this->getUserEmail() == $user->getEmail();
        } else {
            return $userEmail && $this->getUserEmail() == $userEmail;
        }
    }

    public function getUserDisplayName()
    {
        return in_array($this->getUserRole(), [UserRoles::Anonymous, UserRoles::GoGoBot]) ? '' : $this->getUserEmail();
    }

    // used for Report and Vote children class. Overwrite this function like in UserInteractionContribution
    public function toJson()
    {
        $result = '{';
        $result .= '"type":'.$this->getType();
        $result .= ', "value":'.$this->getValue();
        $result .= ', "comment":'.json_encode($this->getComment());
        $result .= ', "userEmail":"'.$this->getUserEmail().'"';
        $result .= ', "userRole" :'.$this->getUserRole();
        $result .= ', "createdAt" :"'.$this->formatDate($this->getCreatedAt()).'"';
        $result .= '}';

        return $result;
    }

    protected function formatDate($date)
    {
        if (!$date) {
            return '';
        }
        return $date->format(\DateTime::ATOM);
    }

    // ------------------------ GETTER AND SETTERS ----------------------------

    /**
     * Get id.
     *
     * @return id $id
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Set type.
     *
     * @param int $type
     *
     * @return $this
     */
    public function setType($type)
    {
        $this->type = $type;

        return $this;
    }

    /**
     * Get type.
     *
     * @return int $type
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * Set userRole.
     *
     * @param string $userRole
     *
     * @return $this
     */
    public function setUserRole($userRole)
    {
        $this->userRole = $userRole;

        return $this;
    }

    /**
     * Get userRole.
     *
     * @return string $userRole
     */
    public function getUserRole()
    {
        return $this->userRole;
    }

    /**
     * Set userEmail.
     *
     * @param string $userEmail
     *
     * @return $this
     */
    public function setUserEmail($userEmail)
    {
        $this->userEmail = $userEmail;

        return $this;
    }

    /**
     * Get userEmail.
     *
     * @return string $userEmail
     */
    public function getUserEmail()
    {
        return $this->userEmail;
    }

    /**
     * Set element.
     *
     * @param App\Document\Element $element
     *
     * @return $this
     */
    public function setElement(\App\Document\Element $element)
    {
        $this->element = $element;

        return $this;
    }

    /**
     * Get element.
     *
     * @return App\Document\Element $element
     */
    public function getElement()
    {
        return $this->element;
    }

    /**
     * Set createdAt.
     *
     * @param date $createdAt
     *
     * @return $this
     */
    public function setCreatedAt($createdAt)
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    /**
     * Get createdAt.
     *
     * @return date $createdAt
     */
    public function getCreatedAt()
    {
        return $this->createdAt;
    }

    /**
     * Set updatedAt.
     *
     * @param date $updatedAt
     *
     * @return $this
     */
    public function setUpdatedAt($updatedAt)
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    /**
     * Get updatedAt.
     *
     * @return date $updatedAt
     */
    public function getUpdatedAt()
    {
        return $this->updatedAt;
    }

    /**
     * Set resolvedBy.
     *
     * @param string $resolvedBy
     *
     * @return $this
     */
    public function setResolvedBy($resolvedBy)
    {
        $this->resolvedBy = $resolvedBy;

        return $this;
    }

    /**
     * Get resolvedBy.
     *
     * @return string $resolvedBy
     */
    public function getResolvedBy()
    {
        return $this->resolvedBy;
    }

    /**
     * Set resolvedMessage.
     *
     * @param string $resolvedMessage
     *
     * @return $this
     */
    public function setResolvedMessage($resolvedMessage)
    {
        $this->resolvedMessage = $resolvedMessage;

        return $this;
    }

    /**
     * Get resolvedMessage.
     *
     * @return string $resolvedMessage
     */
    public function getResolvedMessage()
    {
        return $this->resolvedMessage;
    }

    /**
     * Add webhookPost.
     *
     * @param App\Document\WebhookPost $webhookPost
     */
    public function addWebhookPost(\App\Document\WebhookPost $webhookPost)
    {
        $this->webhookPosts[] = $webhookPost;
    }

    /**
     * Remove webhookPost.
     *
     * @param App\Document\WebhookPost $webhookPost
     */
    public function removeWebhookPost(\App\Document\WebhookPost $webhookPost)
    {
        $this->webhookPosts->removeElement($webhookPost);
    }

    /**
     * Get webhookPosts.
     *
     * @return \Doctrine\Common\Collections\Collection $webhookPosts
     */
    public function getWebhookPosts()
    {
        return $this->webhookPosts;
    }

    public function clearWebhookPosts()
    {
        $this->webhookPosts = [];

        return $this;
    }
}
