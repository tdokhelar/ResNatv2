<?php

namespace App\Document;
use Doctrine\Bundle\MongoDBBundle\Validator\Constraints\Unique;
use Doctrine\ODM\MongoDB\Mapping\Annotations as MongoDB;

/** 
 * @MongoDB\EmbeddedDocument
 * @Unique(fields="name")
 * */
class GeojsonLayer
{
    /** @MongoDB\Field(type="string") */
    public $name;
    
    /** @MongoDB\Field(type="string") */
    public $url;
    
    /** @MongoDB\Field(type="boolean") */
    public $optionnal;

    /**
     * Set name.
     *
     * @param string $name
     *
     * @return $this
     */
    public function setName($name)
    {
        $this->name = $name;

        return $this;
    }

    /**
     * Get name.
     *
     * @return string $name
     */
    public function getName()
    {
        return $this->name;
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
     * Set optionnal.
     *
     * @param string $optionnal
     *
     * @return $this
     */
    public function setOptionnal($optionnal)
    {
        $this->optionnal = $optionnal;

        return $this;
    }

    /**
     * Get optionnal.
     *
     * @return string $optionnal
     */
    public function getOptionnal()
    {
        return $this->optionnal;
    }
}