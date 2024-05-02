<?php

namespace App\Services;

use App\Document\Coordinates;
use App\Document\Element;
use App\Document\ElementFile;
use App\Document\ElementImage;
use App\Document\ElementStatus;
use App\Document\ModerationState;
use App\Document\OpenHours;
use App\Document\OptionValue;
use App\Document\PostalAddress;
use Doctrine\ODM\MongoDB\DocumentManager;
use Geocoder\Query\GeocodeQuery;
use Geocoder\ProviderAggregator;

class ElementImportOneService
{
    private $dm;
    private $geocoder;
    private $elementFormService;
    protected $optionIdsToAddToEachElement = [];
    private $lastGeocodeTimestamp;

    protected $mainConfigHaveChangedSinceLastImport;
    /**
     * Constructor.
     */
    public function __construct(DocumentManager $dm, ProviderAggregator $geocoder,
                                ElementFormService $elementFormService)
    {
        $this->dm = $dm;
        $this->geocoder = $geocoder->using('nominatim');
        $this->elementFormService = $elementFormService;
        $this->currentRow = [];
        $this->config = false;
    }
    
    public function getConfig()
    {
        if (!$this->config) $this->config = $this->dm->get('Configuration')->findConfiguration();
        return $this->config;
    }

    public function initialize($import)
    {
        $this->optionIdsToAddToEachElement = [];
        foreach ($import->getOptionsToAddToEachElement() as $option) {
            $this->optionIdsToAddToEachElement[] = $option->getId();
            $this->dm->persist($option->getParent()); // Strange bug, need to manually persist parent
        }

        $this->mainConfigHaveChangedSinceLastImport = $import->getMainConfigUpdatedAt() > $import->getLastRefresh();
    }

    public function createElementFromArray($row, $import)
    {
        $updateExisting = false; // if we create a new element or update an existing one

        // adds missings fields instead of checking if each field is set before accessing
        $originalRow = array_merge(array(), $row);
        $missingFields = array_diff(Element::CORE_FIELDS, array_keys($row));
        foreach ($missingFields as $missingField) {
            // Do not init owner otherwise it get reinialized at each import
            if ($missingField != 'owner') {
                $row[$missingField] = ('categories' == $missingField) ? [] : '';
            }
        }

        $element = null;

        if ($row['id']) {
            if (in_array($row['id'], $import->getIdsToIgnore())) {
                return $this->resultData($element, 'ignored');
            }
            $qb = $this->dm->query('Element');
            $qb->field('source')->references($import);
            $qb->field('oldId')->equals(''.$row['id']);
            $qb->field('status')->notEqual(ElementStatus::ModifiedPendingVersion);
            $element = $qb->getQuery()->getSingleResult();
        } elseif (!empty($row['gogoId'])) {
            $element = $this->dm->get('Element')->find($row['gogoId']);
            if ($element) $row = $this->updateRowWithExistingElement($row, $originalRow, $element);
        } elseif (is_string($row['name']) && strlen($row['name']) > 0) {
            $qb = $this->dm->query('Element');
            $qb->field('source')->references($import);
            $qb->field('status')->notEqual(ElementStatus::ModifiedPendingVersion);
            $qb->field('name')->equals($row['name']);

            if (is_string($row['latitude']) && strlen($row['latitude']) > 0 && is_string($row['longitude']) && strlen($row['longitude']) > 0) {
                $geo = new Coordinates($row['latitude'], $row['longitude']);
                $qb->field('geo.latitude')->equals($geo->getLatitude());
                $qb->field('geo.longitude')->equals($geo->getLongitude());
                $element = $qb->getQuery()->getSingleResult();
            } elseif (strlen($row['streetAddress']) > 0) {
                if (strlen($row['streetNumber']) > 0) {
                    $qb->field('address.streetNumber')->equals($row['streetNumber']);
                }
                if (strlen($row['streetAddress']) > 0) {
                    $qb->field('address.streetAddress')->equals($row['streetAddress']);
                }
                if (strlen($row['addressLocality']) > 0) {
                    $qb->field('address.addressLocality')->equals($row['addressLocality']);
                }
                if (strlen($row['postalCode']) > 0) {
                    $qb->field('address.postalCode')->equals($row['postalCode']);
                }
                if (strlen($row['addressCountry']) > 0) {
                    $qb->field('address.addressCountry')->equals($row['addressCountry']);
                }
                $element = $qb->getQuery()->getSingleResult();
            }
        }

        if ($element) { // if the element already exists, we update it
            $updateExisting = true;

            // do nothing if element is not editable
            if (!$element->isFullyEditable()) {
                return $this->resultData($element, 'nothing_to_do');
            }

            // if main import config has change, we should reimport anyway
            if (!$this->mainConfigHaveChangedSinceLastImport) {
                $updatedAtField = $import->getFieldToCheckElementHaveBeenUpdated();
                // if updatedAtField hasn't change, nothing to do
                if ($updatedAtField && array_key_exists($updatedAtField, $row)) {
                    if ($row[$updatedAtField] && $row[$updatedAtField] == $element->getCustomProperty($updatedAtField)) {
                        $element->setPreventJsonUpdate(true);
                        $this->dm->persist($element);
                        return $this->resultData($element, 'nothing_to_do');
                    }
                }
            }

            // resetting "geoloc" and "no options" modearation state so it will be calculated again
            if ($element->getModerationState() < 0) {
                $element->setModerationState(ModerationState::NotNeeded);
            }
        } else {
            $element = new Element();
            $element->setStatus(ElementStatus::Imported);
        }
        $originalElement = $element->clone();
        $this->currentRow = $row;

        $this->createOptionValues($element, $row, $import);
        if ($import->getPreventImportIfNoCategories() && ModerationState::NoOptionProvided == $element->getModerationState()) {
            return $this->resultData($element, 'no_category');
        }

        if ($row['id']) $element->setOldId($row['id']);
        if ($row['name']) $element->setName($row['name']);

        $oldFormatedAdress = $element->getFormatedAddress();

        $address = $element->getAddress();
        $addressFields = ['streetNumber', 'streetAddress', 'addressLocality', 'postalCode', 'addressCountry', 'customFormatedAddress'];
        foreach($addressFields as $field) {
            $setter = 'set'.ucfirst($field);
             // Update only if the value was there on the originalRow
            if (array_key_exists($field, $originalRow)) {
                $address->$setter($row[$field]);
            }
        }
        $element->setAddress($address);

        if (empty($row['gogoId'])) $element->setSource($import);
        // Override sourceKey for standard import
        if (!$import->isDynamicImport() && (strlen($row['source']) > 0 && 'Inconnu' != $row['source']))
            $element->setSourceKey($row['source']);


        if (array_key_exists('owner', $originalRow)) {
            $element->setUserOwnerEmail($row['owner']);
        }
        if (array_key_exists('email', $originalRow)) {
            $element->setEmail($row['email']);
        }

        $lat = $row['latitude'];
        $lng = $row['longitude'];
        try {
            if (is_object($lat) || is_array($lat) || 0 == strlen($lat) || is_object($lng) || 0 == strlen($lng) || 'null' == $lat || null == $lat) {
                $lat = 0; $lng = 0;  
                $newFormatedAddress = $element->getAddress()->getFormatedAddress();
                // Geocode if necessary  
                if ($newFormatedAddress == $oldFormatedAdress) {
                    $lat = $element->getGeo()->getLatitude();
                    $lng = $element->getGeo()->getLongitude();
                } 
                if ($lat == 0 && $lng == 0 && $import->getGeocodeIfNecessary() && $newFormatedAddress && strlen($newFormatedAddress) > 0) {
                    $newGeocodeTimestamp = time();
                    if ($newGeocodeTimestamp == $this->lastGeocodeTimestamp) {
                        sleep(1); // sleep to comply with nominatim policy (1req/s)
                    }
                    
                    $config = $this->getConfig();
                    $geocodingBoundsType= $config->getGeocodingBoundsType();

                    $query = GeocodeQuery::create($newFormatedAddress);
                    
                    $forcedCountryCode = $element->getAddress()->addressCountry;
                    if (strlen($forcedCountryCode) !== 2) {
                        $forcedCountryCode = false;
                    }
                    $countryCodes = [];
                    $bounds = [];
                    if ($forcedCountryCode) {
                        $countryCodes[] = $forcedCountryCode;
                    } else {
                        switch ($geocodingBoundsType) {
                            case 'countryCodes':
                                $countryCodes = explode(",",$config->getGeocodingBoundsByCountryCodes());
                                break;
                            case 'defaultView':
                                $bounds = $config->getDefaultBounds();
                                break;
                            case 'viewPicker':
                                $bounds = $config->getGeocodingBounds(); 
                                break;
                        }
                    }
                    if (count($countryCodes) > 0) {
                        $query = $query->withData('countrycodes', $countryCodes);
                    }
                    if (count($bounds) > 0) {
                        // (longitude first)
                        $bounds = array_merge(array_reverse($bounds[0]), array_reverse($bounds[1]));
                        $query = $query->withData('bounded', true);
                        $query = $query->withData('viewbox', $bounds);
                    }

                    $result = $this->geocoder
                        ->geocodeQuery($query)
                        ->first()
                        ->getCoordinates();

                    $this->lastGeocodeTimestamp = $newGeocodeTimestamp;
                    
                    $lat = $result->getLatitude();
                    $lng = $result->getLongitude();
                }
            }
        } catch (\Exception $e) {}
        
        if (0 == $lat || 0 == $lng) {
            $element->setModerationState(ModerationState::GeolocError);
        }
        
        $element->setGeo(new Coordinates($lat, $lng));
        if (isset($originalRow['images'])) $this->createImages($element, $row);
        if (isset($originalRow['files'])) $this->createFiles($element, $row);
        $this->createOpenHours($element, $row);
        unset($row['osm_opening_hours']);
        $this->saveCustomFields($element, $row);
        $somethingHasChanged = count($element->getChangeset($this->dm, $originalElement)) > 0;
        // Return if nothing have changed
        if ($updateExisting && !$somethingHasChanged) {            
            $element->setPreventJsonUpdate(true);
            $this->dm->persist($element);
            return $this->resultData($element, 'nothing_to_do');
        }

        $element->setCurrentlyEditedBy('import');
        $element = $this->elementFormService->save($element, $originalElement, null, !$import->getModerateElements());

        $element->setPreventLinksUpdate(true); // Check the links between elements all at once at the end of the import
        $this->dm->persist($element);

        return $this->resultData($element, $updateExisting ? 'updated' : 'created');
    }

    private function resultData($element, $state)
    {
        $result = ['status' => $state];
        if ($element) {
            $result['id'] = $element->getId();
            if ($element->isPendingModification()){
                $result['id_pending_modified'] = $element->getModifiedElement()->getId();
            }
        }
        return $result;
    }

    private function saveCustomFields($element, $raw_data)
    {
        $coreFields = Element::CORE_FIELDS;
        $coreFields[] = 'gogoId';
        $customFields = array_diff(array_keys($raw_data), $coreFields);
        $customData = [];
        foreach ($customFields as $customField) {
            if ($customField && is_string($customField)) {
                $customData[$customField] = $raw_data[$customField];
            }
        }
        $element->setCustomData($customData);
    }

    private function createImages($element, $row)
    {
        $element->resetImages();
        $images_raw = $row['images'];
        if (is_string($images_raw) && strlen($images_raw) > 0) {
            $images = explode(',', $row['images']);
        } elseif (is_array($images_raw)) {
            $images = $images_raw;
        } else {
            $keys = array_keys($row);
            $image_keys = array_filter($keys, function ($key) { return startsWith($key, 'image'); });
            $images = array_map(function ($key) use ($row) { return $row[$key]; }, $image_keys);
        }

        if (0 == count($images)) {
            return;
        }

        foreach ($images as $imageUrl) {
            if (is_string($imageUrl) && strlen($imageUrl) > 5) {
                $elementImage = new ElementImage();
                $elementImage->setExternalImageUrl($imageUrl);
                $element->addImage($elementImage);
            }
        }
    }

    private function createFiles($element, $row)
    {
        $element->resetFiles();
        $files = $row['files'];
        if (is_string($files) && strlen($files) > 0) {
            $files = explode(',', $files);
        }
        if (!is_array($files) || 0 == count($files)) {
            return;
        }
        foreach ($files as $url) {
            if (is_string($url) && strlen($url) > 5) {
                $elementFile = new ElementFile();
                $elementFile->setFileUrl($url);
                $name = explode('/', $url);
                $name = end($name);
                $elementFile->setFileName($name);
                $element->addFile($elementFile);
            }
        }
    }

    private function createOpenHours($element, $row)
    {
        if (isset($row['osm_opening_hours'])) {
            try {
                $oh = new OpenHours();
                $oh->buildFromOsm($row['osm_opening_hours']);
                $element->setOpenHours($oh);                
            }
            catch(\Exception $e) {;}
        }
        else if(isset($row['openHours']) && is_associative_array($row['openHours'])) {
            $element->setOpenHours(new OpenHours($row['openHours']));
        }
    }

    private function createOptionValues($element, $row, $import)
    {
        if (!$import->isHandlingCategories()) return;
        $element->resetOptionsValues();
        $optionsIdAdded = [];
        $defaultOption = ['index' => 0, 'description' => ''];
        foreach ($row['categories'] as $option) {
            $option = array_merge($defaultOption, $option);
            if (!in_array($option['id'], $optionsIdAdded)) {
                $optionsIdAdded[] = $this->addOptionValue($element, $option['id'], $option['index'], $option['description']);
            }
        }

        if ($import->getNeedToHaveOptionsOtherThanTheOnesAddedToEachElements()) {
            // checking option number before adding optionIdsToAddToEachElement
            if (0 == count($element->getOptionValues())) {
                $element->setModerationState(ModerationState::NoOptionProvided);
            }
        }
        // Manually add some options to each element imported
        foreach ($this->optionIdsToAddToEachElement as $optionId) {
            if (!in_array($optionId, $optionsIdAdded)) {
                $optionsIdAdded[] = $this->addOptionValue($element, $optionId);
            }
        }
        if (0 == count($element->getOptionValues())) {
            $element->setModerationState(ModerationState::NoOptionProvided);
        }
    }

    private function addOptionValue($element, $id, $index = 0, $description = '')
    {
        if (!$id || '0' == $id || 0 == $id) {
            return;
        }
        $optionValue = new OptionValue();
        $optionValue->setOptionId($id);
        $optionValue->setIndex($index);
        $optionValue->setDescription($description);
        $element->addOptionValue($optionValue);

        return $id;
    }

    private function updateRowWithExistingElement($row, $originalRow, $element)
    {
        if (!isset($originalRow['email'])) $row['email'] = $element->getEmail();
        if (!isset($originalRow['latitude'])) $row['latitude'] = $element->getGeo()->getLatitude();
        if (!isset($originalRow['longitude'])) $row['longitude'] = $element->getGeo()->getLongitude();
        return $row;
    }
}
