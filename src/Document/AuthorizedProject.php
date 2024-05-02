<?php

namespace App\Document;

use Doctrine\ODM\MongoDB\Mapping\Annotations as MongoDB;

/** @MongoDB\Document */
class AuthorizedProject implements \JsonSerializable
{
    /**
     * @var int
     * @MongoDB\Id(strategy="INCREMENT")
     */
    private $id;

    /**
     * Authorized Project URL
     *
     * @MongoDB\Field(type="string")
     */
    private $url;

    /**
     * apiKey
     *
     * @MongoDB\Field(type="string")
     */
    private $apiKey;

    /**
     * isActivated
     *
     * @MongoDB\Field(type="boolean")
     */
    private $isActivated = false;

    public function __construct()
    {
        $this->generateApiKey();
    }

    public function __toString()
    {
        return $this->getUrl();
    }

    public function jsonSerialize()
    {
        return get_object_vars($this);
    }

    /**
     * Get id.
     *
     * @return int_id $id
     */
    public function getId()
    {
        return $this->id;
    }
    
    /**
     * Set apiKey.
     *
     * @param string $apiKey
     *
     * @return $this
     */
    public function setApiKey($apiKey)
    {
        $this->apiKey = $apiKey;

        return $this;
    }

    /**
     * Get apiKey.
     *
     * @return string $apiKey
     */
    public function getApiKey()
    {
        return $this->apiKey;
    }
    
    public function generateApiKey()
    {
        $this->setApiKey(uniqId());
    }

    /**
     * Set url.
     *
     * @param string $url
     *
     * @return $this
     */
    public function setUrl($url)
    {
        $this->url = $url;

        return $this;
    }

    /**
     * Get url.
     *
     * @return string $url
     */
    public function getUrl()
    {
        return $this->url;
    }

    /**
     * Set isActivated.
     *
     * @param string $isActivated
     *
     * @return $this
     */
    public function setIsActivated($isActivated)
    {
        $this->isActivated = $isActivated;

        return $this;
    }

    /**
     * Get isActivated.
     *
     * @return string $isActivated
     */
    public function getIsActivated()
    {
        return $this->isActivated;
    }
}
