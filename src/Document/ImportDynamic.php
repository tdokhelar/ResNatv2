<?php

namespace App\Document;

use Datetime;
use Doctrine\ODM\MongoDB\Mapping\Annotations as MongoDB;

/**
 * External source to load dynamically.
 *
 * @MongoDB\Document
 */
class ImportDynamic extends Import
{
    /**
     * Get Data from OpenStreetMap using overpass query
     * @MongoDB\Field(type="string")
     */
    public $osmQueriesJson;
    
    /**
     * @var string
     * @MongoDB\Field(type="int")
     */
    private $refreshFrequencyInDays;

    /**
     * @var date
     *
     * @MongoDB\Field(type="date")
     */
    private $nextRefresh = null;

    /**
     * Users to be tonified when error during import, or where new ontology/taxonomy mapping
     * @MongoDB\ReferenceMany(targetDocument="App\Document\User")
     */
    private $usersToNotify;

    /**
     * Email or Url to contact the source
     * @MongoDB\Field(type="string")
     */
    private $contact;

    /**
     * Whether to allow editing the imported data.
     * Each change will then by sent to the source
     * @MongoDB\Field(type="bool")
     */
    private $isSynchronized = false;

    /**
     * Whether to allow adding the elements to the source.
     * Each new element will be sent to the source
     * Only elements that have categories used by this import will be sent
     * @MongoDB\Field(type="bool")
     */
    private $allowAdd = false;

    /**
     * APIKey given by gogocarto source project
     * @MongoDB\Field(type="string")
     */
    private $apiKey;

    public function isDynamicImport()
    {
        return true;
    }

    public function updateNextRefreshDate()
    {
        if (0 == $this->getRefreshFrequencyInDays()) {
            $this->setNextRefresh(null);
        } else {
            $interval = new \DateInterval('P'.$this->getRefreshFrequencyInDays().'D');
            $date = new DateTime();
            $date->setTimestamp(time());
            $this->setNextRefresh($date->add($interval));
        }
    }

    /**
     * Set refreshFrequencyInDays.
     *
     * @param int $refreshFrequencyInDays
     *
     * @return $this
     */
    public function setRefreshFrequencyInDays($refreshFrequencyInDays)
    {
        $this->refreshFrequencyInDays = $refreshFrequencyInDays;
        $this->updateNextRefreshDate();

        return $this;
    }

    /**
     * Get refreshFrequencyInDays.
     *
     * @return int $refreshFrequencyInDays
     */
    public function getRefreshFrequencyInDays()
    {
        return $this->refreshFrequencyInDays;
    }

    /**
     * Set nextRefresh.
     *
     * @param date $nextRefresh
     *
     * @return $this
     */
    public function setNextRefresh($nextRefresh)
    {
        $this->nextRefresh = $nextRefresh;

        return $this;
    }

    /**
     * Get nextRefresh.
     *
     * @return date $nextRefresh
     */
    public function getNextRefresh()
    {
        return $this->nextRefresh;
    }

    public function getOsmQueriesJson()
    {
        return $this->osmQueriesJson ?? '{"queries": [], "address": "", "bounds": null}';
    }

    public function getOsmQueries()
    {
        return json_decode($this->getOsmQueriesJson())->queries;
    }

    public function setOsmQueriesJson($json) 
    {
        $this->osmQueriesJson = $json;
        return $this;
    }

    /**
     * Get users to be tonified when error during import, or where new ontology/taxonomy mapping
     */ 
    public function getUsersToNotify()
    {
        return $this->usersToNotify;
    }

    /**
     * Set users to be tonified when error during import, or where new ontology/taxonomy mapping
     *
     * @return  self
     */ 
    public function setUsersToNotify($usersToNotify)
    {
        $this->usersToNotify = $usersToNotify;

        return $this;
    }

    /**
     * Get the value of isSynchronized
     */ 
    public function getIsSynchronized()
    {
        return $this->isSynchronized;
    }

    /**
     * Set the value of isSynchronized
     *
     * @return  self
     */ 
    public function setIsSynchronized($isSynchronized)
    {
        $this->isSynchronized = $isSynchronized;

        return $this;
    }

    /**
     * Get email or Url to contact the source
     */ 
    public function getContact()
    {
        return $this->contact;
    }

    /**
     * Set email or Url to contact the source
     *
     * @return  self
     */ 
    public function setContact($contact)
    {
        $this->contact = $contact;

        return $this;
    }

    /**
     * Get whether to allow adding the elements to the source.
     */
    public function getAllowAdd()
    {
        return $this->allowAdd;
    }

    /**
     * Set whether to allow adding the elements to the source.
     *
     * @return  self
     */
    public function setAllowAdd($allowAdd)
    {
        $this->allowAdd = $allowAdd;

        return $this;
    }
    
    /**
     * Force value of moderateElements to false when synchronized
     */
    public function getModerateElements()
    {
        if ($this->getIsSynchronized()) {
            return false;            
        } else {
            return $this->moderateElements;
        }
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

}
