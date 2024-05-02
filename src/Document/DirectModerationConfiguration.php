<?php

namespace App\Document;

use Doctrine\ODM\MongoDB\Mapping\Annotations as MongoDB;

/** @MongoDB\EmbeddedDocument */
class DirectModerationConfiguration extends FeatureConfiguration
{
    /** @MongoDB\Field(type="bool") */
    public $allow_owner;

    public function __construct($active = false, $iframe = false, $anon = false, $user = false, $admin = false, $owner = false)
    {
        $this->setActive($active);
        $this->setActiveInIframe($iframe);
        $this->setAllowRoleAnonymous($anon);
        $this->setAllowRoleUser($user);
        $this->setAllowRoleAdmin($admin);
        $this->setAllowOwner($owner);
    }

    /**
     * Set allowOwner.
     *
     * @param bool $allow_owner
     *
     * @return $this
     */
    public function setAllowOwner($allow_owner)
    {
        $this->allow_owner = $allow_owner;

        return $this;
    }

    public function setAllow_owner($value)
    {
        return $this->setAllowOwner($value);
    }

    /**
     * Get allowOwner.
     *
     * @return bool $allow_owner
     */
    public function getAllowOwner()
    {
        return $this->allow_owner !== false;
    }
}
