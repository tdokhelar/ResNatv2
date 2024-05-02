<?php

namespace App\Document\Configuration;

use Doctrine\ODM\MongoDB\Mapping\Annotations as MongoDB;

/** @MongoDB\EmbeddedDocument */
class ConfigurationSubscription
{
    /**
     * List of the properties which will trigger change event
     *
     * @MongoDB\Field(type="collection")
     */
    public $subscriptionProperties = [];

    /**
     * Set subscriptionProperties.
     *
     * @param collection $subscriptionProperties
     *
     * @return $this
     */
    public function setSubscriptionProperties($subscriptionProperties)
    {
        $this->subscriptionProperties = $subscriptionProperties;

        return $this;
    }

    /**
     * Get subscriptionProperties.
     *
     * @return collection $subscriptionProperties
     */
    public function getSubscriptionProperties()
    {
        return $this->subscriptionProperties;
    }
}
