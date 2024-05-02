<?php

namespace App\Document;

use Doctrine\ODM\MongoDB\Mapping\Annotations as MongoDB;
use JMS\Serializer\Annotation\Expose;

/** @MongoDB\EmbeddedDocument */
class Coordinates
{
    /**
     * @Expose
     * @MongoDB\Field(type="float")
     */
    public $latitude = 0;

    /**
     * @Expose
     * @MongoDB\Field(type="float")
     */
    public $longitude = 0;

    public function __construct($lat = null, $lng = null)
    {
        $this->setLatitude((float) $lat);
        $this->setLongitude((float) $lng);
    }

    public function toJson()
    {
        return '{"latitude":'.$this->getRoundedLatitude().',"longitude":'.$this->getRoundedLongitude().'}';
    }

    public function round($geo) {
        return ($geo * 1000000) % (180 * 1000000) / 1000000;
    }

    public function getRoundedLatitude() {
        return $this->round($this->getLatitude());
    }

    public function getRoundedLongitude() {
        return $this->round($this->getLongitude());
    }

    /**
     * Set latitude.
     *
     * @param float $latitude
     *
     * @return $this
     */
    public function setLatitude($latitude)
    {
        $this->latitude = $latitude;

        return $this;
    }

    /**
     * Get latitude.
     *
     * @return float $latitude
     */
    public function getLatitude()
    {
        return $this->latitude;
    }

    /**
     * Set longitude.
     *
     * @param float $longitude
     *
     * @return $this
     */
    public function setLongitude($longitude)
    {
        $this->longitude = $longitude;

        return $this;
    }

    /**
     * Get longitude.
     *
     * @return float $longitude
     */
    public function getLongitude()
    {
        return $this->longitude;
    }
}
