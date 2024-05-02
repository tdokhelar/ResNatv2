<?php

namespace App\Document;

use Doctrine\ODM\MongoDB\Mapping\Annotations as MongoDB;
use Gedmo\Mapping\Annotation as Gedmo;

/**
 * TileLayer.
 *
 * @MongoDB\Document(repositoryClass="App\Repository\TileLayerRepository")
 */
class TileLayer
{
    /**
     * @var int
     *
     * @MongoDB\Id(strategy="INCREMENT")
     */
    private $id;

    /**
     * @var date
     *
     * @MongoDB\Field(type="date") @MongoDB\Index
     * @Gedmo\Timestampable(on="create")
     */
    private $createdAt;
    
    /** @MongoDB\Field(type="string") */
    public $name;

    /** @MongoDB\Field(type="string") */
    public $url;

    /** @MongoDB\Field(type="string") */
    public $attribution;

    /** @MongoDB\Field(type="int") */
    public $maxZoom;

    /**
     * @Gedmo\SortablePosition
     * @MongoDB\Field(type="int")
     */
    private $position;

    public function __toString()
    {
        return $this->getName() ? $this->getName() : '';
    }

    public function toJson()
    {
        return json_encode($this);
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
     * Set created.
     *
     * @param date $created
     *
     * @return $this
     */
    public function setCreatedAt($createdAt)
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    /**
     * Get created.
     *
     * @return date $created
     */
    public function getCreatedAt()
    {
        return $this->createdAt ?? new \DateTime();
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
     * Set position.
     *
     * @param int $position
     *
     * @return $this
     */
    public function setPosition($position)
    {
        $this->position = $position;

        return $this;
    }

    /**
     * Get position.
     *
     * @return int $position
     */
    public function getPosition()
    {
        return $this->position;
    }

    /**
     * Set attribution.
     *
     * @param string $attribution
     *
     * @return $this
     */
    public function setAttribution($attribution)
    {
        $this->attribution = $attribution;

        return $this;
    }

    /**
     * Get attribution.
     *
     * @return string $attribution
     */
    public function getAttribution()
    {
        return $this->attribution;
    }

     /**
      * Get the value of maxZoom
      */ 
     public function getMaxZoom()
     {
          return $this->maxZoom;
     }

     /**
      * Set the value of maxZoom
      *
      * @return  self
      */ 
     public function setMaxZoom($maxZoom)
     {
          $this->maxZoom = $maxZoom;

          return $this;
     }
}
