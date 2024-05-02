<?php

namespace App\Document\Configuration;
use Doctrine\Bundle\MongoDBBundle\Validator\Constraints\Unique;
use Doctrine\ODM\MongoDB\Mapping\Annotations as MongoDB;

/** 
 * @MongoDB\Document
 * @Unique(fields="name")
 * */
class ConfigurationExport
{
    /**
     * @var int
     * @MongoDB\Id(strategy="INCREMENT")
     */
    private $id;
    
    /** @MongoDB\Field(type="string")
     */
    public $name;
    
    /**
     * List of the properties which will be exported
     * @MongoDB\Field(type="collection")
     */
    public $exportProperties = [];

    /**
     * Get id.
     *
     * @return int_id $id
     */
    public function getId()
    {
        return $this->id;
    }

    public function __toString()
    {
        return $this->getName();
    }
    
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
     * Set exportProperties.
     *
     * @param collection $exportProperties
     *
     * @return $this
     */
    public function setExportProperties($exportProperties)
    {
        $this->exportProperties = $exportProperties;

        return $this;
    }

    /**
     * Get exportProperties.
     *
     * @return collection $exportProperties
     */
    public function getExportProperties()
    {
        return $this->exportProperties;
    }
}
