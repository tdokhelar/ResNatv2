<?php

namespace App\Document;

use Doctrine\ODM\MongoDB\Mapping\Annotations as MongoDB;
use App\Enum\UserInteractionType;
/** @MongoDB\Document */
class UserInteractionContribution extends UserInteraction
{
    /**
     * @var int
     *          ElementStatus
     * @MongoDB\Field(type="int")
     * @MongoDB\Index
     */
    private $status = null;

    /**
     * @var string
     *
     * externalOperator
     * filled only if the contribution come from another gogocarto project 
     * contains the gogocarto project url
     *
     * @MongoDB\Field(type="string")
     */
    protected $externalOperator = null;

    /**
     * @var \stdClass
     * @MongoDB\Field(type="collection")
     * EFor batch contributions, we stored all elements ids concerned
     */
    protected $elementIds;

    /**
     * @MongoDB\Field(type="hash")
     */
    private $changeset = [];

    /**
     * @var \stdClass
     *
     * When user propose a new element, or a modification, the element status became "pending", and other
     * users can vote to validate or not the add/modification
     *
     * @MongoDB\ReferenceMany(targetDocument="App\Document\UserInteractionVote", cascade={"all"})
     */
    private $votes = [];

    public function isPending()
    {
        return $this->status == null;
    }

    public function isAddToOsm()
    {
        return in_array($this->getType(), [UserInteractionType::Add, UserInteractionType::PendingAdd]) &&
               $this->getElement()->isPendingToBeAddedToOsm();
    }

    /* if a contribution has been accepted or refused, but is not still pending */
    public function isResolved()
    {
        return !in_array($this->status, [null, ElementStatus::PendingModification, ElementStatus::PendingAdd]);
    }

    public function hasBeenAccepted()
    {
        return null !== $this->status && $this->status > 0;
    }

    public function countAsValidContributionFrom($userEmail)
    {
        return $this->getUserEmail() == $userEmail
               && in_array($this->getType(), [UserInteractionType::Add, UserInteractionType::Edit, UserInteractionType::PendingAdd, UserInteractionType::PendingEdit])
               && $this->getStatus() > 0
               && ElementStatus::ModifiedByOwner != $this->getStatus();
    }

    public function __construct()
    {
        $this->votes = new \Doctrine\Common\Collections\ArrayCollection();
    }

    public function toJson()
    {
        $result = '{';
        $result .= '"type":'.$this->getType();
        if ($this->getStatus()) {
            $result .= ', "status":'.$this->getStatus();
        }
        $result .= ', "user":'.json_encode($this->getUserDisplayName());
        $result .= ', "userRole":'.$this->getUserRole();
        $result .= ', "externalOperator":'.json_encode($this->getExternalOperator());
        $result .= ', "resolvedMessage":'.json_encode($this->getResolvedMessage());
        $result .= ', "resolvedBy":"'.$this->getResolvedBy().'"';
        $result .= ', "createdAt":"'.$this->formatDate($this->getCreatedAt()).'"';
        $result .= ', "updatedAt":"'.$this->formatDate($this->getUpdatedAt()).'"';
        $result .= '}';

        return $result;
    }

    /**
     * Set status.
     *
     * @param int $status
     *
     * @return $this
     */
    public function setStatus($status)
    {
        $this->status = $status;

        return $this;
    }

    /**
     * Get status.
     *
     * @return int $status
     */
    public function getStatus()
    {
        return $this->status;
    }

    /**
     * Add vote.
     *
     * @param App\Document\UserInteractionVote $vote
     */
    public function addVote(\App\Document\UserInteractionVote $vote)
    {
        $this->votes[] = $vote;
    }

    /**
     * Remove vote.
     *
     * @param App\Document\UserInteractionVote $vote
     */
    public function removeVote(\App\Document\UserInteractionVote $vote)
    {
        $this->votes->removeElement($vote);
    }

    /**
     * Get votes.
     *
     * @return \Doctrine\Common\Collections\Collection $votes
     */
    public function getVotes()
    {
        return $this->votes;
    }

    /**
     * Set elementIds.
     *
     * @param collection $elementIds
     *
     * @return $this
     */
    public function setElementIds($elementIds)
    {
        $this->elementIds = $elementIds;

        return $this;
    }

    /**
     * Get elementIds.
     *
     * @return collection $elementIds
     */
    public function getElementIds()
    {
        return $this->elementIds;
    }

    public function addElementId($id)
    {
        $this->elementIds[] = $id;

        return $this;
    }

    public function getChangeset()
    {
        return $this->changeset;
    }
    public function setChangeset($changeset)
    {
        $this->changeset = $changeset;
        return $this;
    }
    
    /**
     * Set externalOperator.
     *
     * @param string $externalOperator
     *
     * @return $this
     */
    public function setExternalOperator($externalOperator)
    {
        $this->externalOperator = $externalOperator;

        return $this;
    }

    /**
     * Get externalOperator.
     *
     * @return string $externalOperator
     */
    public function getExternalOperator()
    {
        return $this->externalOperator;
    }

}
