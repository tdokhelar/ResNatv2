<?php

namespace App\Services;

use Doctrine\ODM\MongoDB\DocumentManager;
use App\EventListener\ElementJsonGenerator;
use App\Services\ElementActionService;
use App\Document\Coordinates;
use App\Document\Element;
use App\Document\ElementStatus;
use App\Document\ModerationState;

class ElementDuplicatesService
{
    // To know if an element have already been processed, we use the moderationstate : PotentialDuplicate
    // but as we are processing element in batch, during the same batch data is not persisted and so instead
    // we use this array or element Id that we exclude from the detection
    protected $duplicatesFound = [];

    protected $dupConfig = null;

    public function __construct(DocumentManager $dm, ElementActionService $elementActionService, ElementJsonGenerator $jsonGenerator)
    {
        $this->dm = $dm;
        $this->elementActionService = $elementActionService;
        $this->jsonGenerator = $jsonGenerator;
    }

    private function getDupConfig()
    {
        if ($this->dupConfig === null)
            $this->dupConfig = $this->dm->get('Configuration')->findConfiguration()->getDuplicates();
        return $this->dupConfig;
    }

    public function detectDuplicatesFor($element, $restrictToSources = [])
    {       
        if ($element->getStatus() >= ElementStatus::PendingModification
        && !in_array($element->getId(), $this->duplicatesFound)
        && !$element->isPotentialDuplicate()
        && !$element->isAggregated()) {
            $duplicates = $this->dm->get('Element')->findDuplicatesFor($element, $this->duplicatesFound, $restrictToSources);
            if (count($duplicates) == 0) return null;
            // only keep two duplicates, so get easier to manage for the users (less complicated cases)
            // so we sort duplicates and keep first (best choice)
            usort($duplicates, function($a, $b) use ($element) {
                $aIsValid = $a->isValid();
                $bIsValid = $b->isValid();
                if ($aIsValid != $bIsValid) return $bIsValid;    
                $aIsPerfectMatch = $this->isPerfectMatch($element, $a);
                $bIsPerfectMatch = $this->isPerfectMatch($element, $b);
                if ($aIsPerfectMatch != $bIsPerfectMatch) return $bIsPerfectMatch;                            
                if ($a->getScore() != null && $b->getScore() != null) return $a->getScore() < $b->getScore();
            }); 
            $bestDuplicate = array_shift($duplicates);
            $isPerfectMatch = $this->isPerfectMatch($element, $bestDuplicate);
            $duplicatesToProceed = [$element, $bestDuplicate];
            
            // Choose which duplicate to keep
            $sourcePriorities = $this->getDupConfig()->getSourcePriorityInAutomaticMerge();
            usort($duplicatesToProceed, function($a, $b) use ($sourcePriorities) {
                $aPriority = array_search($a->getSourceKey(), $sourcePriorities);
                $bPriority = array_search($b->getSourceKey(), $sourcePriorities);
                if ($aPriority != $bPriority) {
                    // order by source priority
                    return $aPriority > $bPriority;
                } else {
                    // Or get the more recent
                    try { $diff = $b->getUpdatedAt()->getTimestamp() - $a->getUpdatedAt()->getTimestamp(); }
                    catch (\Exception $e) { return 0; }                    
                    return $diff;
                };
            });   
            
            // Remember them so we do not check duplicate on them again
            foreach ($duplicatesToProceed as $duplicate) {
                $this->duplicatesFound[] = $duplicate->getId();
                $duplicate->setModerationState(ModerationState::PotentialDuplicate);
            }

            $aggregationMode = $this->getDupConfig()->getDuplicatesByAggregation();
            $autoMerge = $isPerfectMatch && $this->getDupConfig()->getAutomaticMergeIfPerfectMatch();
            if ( $aggregationMode && $autoMerge ) {
                $elementToKeep = $this->createAggregate($duplicatesToProceed);
                $duplicate = null;
            } else {
                $elementToKeep = array_shift($duplicatesToProceed);
                $duplicate = array_pop($duplicatesToProceed);
                if ($autoMerge) {
                    $elementToKeep = $this->automaticMerge($elementToKeep, [$duplicate]);
                } else {
                    $elementToKeep->setIsDuplicateNode(true);
                    $elementToKeep->addPotentialDuplicate($duplicate);
                    $duplicate->setModerationState(ModerationState::PotentialDuplicate);
                }
            }
            
            return [
                'automaticMerge' => $autoMerge,
                'elementToKeep' => $elementToKeep,
                'duplicate' => $duplicate,
            ];
        }
    }

    private function isPerfectMatch($element, $duplicate)
    {
        if ($this->getDupConfig()->getUseGlobalSearch()
            && slugify($duplicate->getName()) == slugify($element->getName()))
            return true;

        foreach($this->getDupConfig()->getFieldsToBeUsedForComparaison() as $field) {
            if ($element->getProperty($field) && $duplicate->getProperty($field) == $element->getProperty($field))
                return true;
        }

        return false;
    }

    public function automaticMerge($merged, $duplicates, $updateStatus = true)
    {
        $mergedData = $merged->getData();
        $mergedOptionIds = $merged->getOptionIds();

        foreach ($duplicates as $duplicate) {
            // Auto merge option values
            foreach ($duplicate->getOptionValues() as $dupOptionValue) {
                $optionId = strval($dupOptionValue->getOptionId());
                if (!in_array($optionId, $mergedOptionIds)) {
                    $mergedOptionIds[] = $optionId;
                    $merged->addOptionValue($dupOptionValue);
                }
            }
            // Auto merge custom attributes
            foreach ($duplicate->getData() as $key => $value) {
                if ($value && (!array_key_exists($key, $mergedData) || !$mergedData[$key])) {
                    $mergedData[$key] = $value;
                }
            }
            // merge non duplicates
            foreach ($duplicate->getNonDuplicates() as $nonDup) $merged->addNonDuplicate($nonDup);
            // Auto merge special attributes
            $merged->setImages(array_merge($merged->getImagesArray(), $duplicate->getImagesArray()));
            $merged->setFiles(array_merge($merged->getFilesArray(), $duplicate->getFilesArray()));
            if (!$merged->getOpenHours() && $duplicate->getOpenHours()) {
                $merged->setOpenHours($duplicate->getOpenHours());
            }
            if (!$merged->getUserOwnerEmail() && $duplicate->getUserOwnerEmail()) {
                $merged->setUserOwnerEmail($duplicate->getUserOwnerEmail());
            }
            if (!$merged->getEmail() && $duplicate->getEmail()) {
                $merged->setEmail($duplicate->getEmail());
            }
            if (!$merged->getAddress()->isComplete()) {
                $address = $merged->getAddress();
                $dupAddress = $duplicate->getAddress();
                if (!$address->getStreetNumber() && $dupAddress->getStreetNumber()) {
                    $address->setStreetNumber($dupAddress->getStreetNumber());
                }
                if (!$address->getStreetAddress() && $dupAddress->getStreetAddress()) {
                    $address->setStreetAddress($dupAddress->getStreetAddress());
                }
                if (!$address->getAddressLocality() && $dupAddress->getAddressLocality()) {
                    $address->setAddressLocality($dupAddress->getAddressLocality());
                }
                if (!$address->getAddressCountry() && $dupAddress->getAddressCountry()) {
                    $address->setAddressCountry($dupAddress->getAddressCountry());
                }
                if (!$address->getPostalCode() && $dupAddress->getPostalCode()) {
                    $address->setPostalCode($dupAddress->getPostalCode());
                }
                $merged->setAddress($address);
            }
            $duplicate->setDuplicateOf($merged->getId());
            // Merge status. If one of the duplicate is deleted, then the merged one will be deleted as well
            // If merged one is pending, and duplicate is validated, then merged will be validated
            if ($updateStatus && !$merged->isDeleted() && ($duplicate->getStatus() > $merged->getStatus() || $duplicate->isDeleted()))
                $merged->setStatus($duplicate->getStatus());
            $this->elementActionService->delete($duplicate, false);
        }
        $merged->setModerationState(ModerationState::NotNeeded);
        $merged->setData($mergedData);
        return $merged;
    }
    
    function createAggregate($elementsToAggregate) {
        
        $aggregates = array_filter($elementsToAggregate, function($element) {
            return $element->isAggregate();  
        });
        $nonAggregates = array_filter($elementsToAggregate, function($element) {
            return ! $element->isAggregate();  
        });
        
        // There is already at least 1 aggregate
        if (count($aggregates) > 0) {
            // The chosen one :
            $selectedAggregate = $this->selectElementForAggregation($aggregates);
            // Get aggregated from non chosen ones
            forEach ($aggregates as $aggregate) {
                if ($aggregate !== $selectedAggregate) {
                    $nonAggregates = array_merge($nonAggregates, $aggregate->getAggregatedElements()->toArray());
                    // 1st flush to prevent onflush trouble
                    $this->dm->flush();
                    // Delete non chosen one
                    $this->dm->remove($aggregate);
                    $this->dm->flush();
                }
            }
            // All non aggregate elements found are aggregated into the chosen one
            forEach ($nonAggregates as $elementToAggregate) {
                $selectedAggregate->addAggregatedElement($elementToAggregate);
                $elementToAggregate->setAggregate($selectedAggregate);
            }
        } else {
            // No Aggregate before : we need to to create a new one
            $newAggregate = new Element();
            $newAggregate->setAggregatedElements($elementsToAggregate);
            forEach ($elementsToAggregate as $elementToAggregate) {
                $elementToAggregate->setAggregate($newAggregate);
            }
            $this->refreshAggregate($newAggregate);
            $selectedAggregate = $newAggregate;
        }
        return $selectedAggregate;
    }
    
    function refreshAggregate($aggregate) {
        $aggregatedElements = $aggregate->getAggregatedElements() ?? [];
        if (!is_array($aggregatedElements)) {
            $aggregatedElements = $aggregatedElements->toArray();
        }
        // remove null, can happen in case of deletion
        $aggregatedElements = array_filter($aggregatedElements, function($e) { return $e->getGeo(); });
        
        // If no more aggregated, then delete it
        if (count($aggregatedElements) == 0) {
            $aggregate->setStatus(ElementStatus::Deleted);
            return;
        }

        forEach ($aggregatedElements as $aggregatedElement) {
            $aggregatedElement->setStatus(ElementStatus::Aggregated);
        }
        
        $aggregatedElements = $this->sortsElementsByPriority($aggregatedElements);
        $selectedElement = $aggregatedElements[0];

        $aggregate->setName($selectedElement->getName());
        $aggregate->setGeo($this->getBarycenter($aggregatedElements));
        $aggregate->setAddress($selectedElement->getAddress());
        $aggregate->setOptionValues($this->getAllValues($aggregatedElements, 'getOptionValues'));
        $aggregate->setEmail($this->getAllValues($aggregatedElements, 'getEmail', true));
        $aggregate->setUserOwnerEmail($this->getAllValues($aggregatedElements, 'getUserOwnerEMail', true));
        $aggregate->setData($this->getAllCustomDatas($selectedElement, $aggregatedElements));
        $aggregate->setSourceKey($this->getAllValues($aggregatedElements, 'getSourceKey', true));
        $aggregate->setImages($this->getAllValues($aggregatedElements, 'getImages'));
        $aggregate->setFiles($this->getAllValues($aggregatedElements, 'getFiles'));
        $aggregate->setStatus(ElementStatus::Aggregate);
        $aggregate->updateTimestamp();
        $this->dm->persist($aggregate);
        $this->jsonGenerator->updateJsonRepresentation($aggregate);
    }
    
    function selectElementForAggregation($elements) {
        
        return ($this->sortsElementsByPriority($elements)[0]);
    }
    
    function sortsElementsByPriority($elements) {
        
        if (count($elements) === 0) {
            return false;
        } else {
            $sourcePriorities = $this->getDupConfig()->getSourcePriorityInAutomaticMerge();
            usort($elements, function($a, $b) use ($sourcePriorities) {
                $aPriority = array_search($a->getSourceKey(), $sourcePriorities);
                $bPriority = array_search($b->getSourceKey(), $sourcePriorities);
                if ($aPriority != $bPriority) {
                    // order by source priority
                    return $aPriority > $bPriority;
                } else {
                    // Or get the more recent
                    try { $diff = $b->getUpdatedAt()->getTimestamp() - $a->getUpdatedAt()->getTimestamp(); }
                    catch (\Exception $e) { return 0; }                    
                    return $diff;
                };
            }); 
            return $elements;
        }
    }
    
    function getAllValues($elements, $method, $getFirstValue = false) {
        
        $values = [];
        forEach ($elements as $element) {
            $otherValue = $element->$method();
            if (method_exists($otherValue, 'toArray')) {
                $otherValue = $otherValue->toArray();
            } else if (!is_array($otherValue)) {
                $otherValue = [$otherValue];
            }
            $values = array_merge($values, $otherValue);
        }
        $result = array_unique(array_filter($values)) ?? [];
        return $getFirstValue ? array_shift($result) : $result;
    }
    
    function getBarycenter($elements) {
        
        $totalLat = 0;
        $totalLng = 0;
        forEach ($elements as $element) {
            $totalLat += $element->getGeo()->getLatitude();
            $totalLng += $element->getGeo()->getLongitude();
        }
        $lat = $totalLat / count($elements);
        $lng = $totalLng / count($elements);
        $geo = new Coordinates($lat, $lng);
        return $geo;
    }
    
    function getAllCustomDatas($selectedElement, $elements) {
        
        $customDatas = $selectedElement->getData();
        forEach ($elements as $element) {
            $datas = $element->getData();
            forEach ($datas as $key => $data) {
                if (!array_key_exists($key, $customDatas) || !$customDatas[$key]) {
                    $customDatas[$key] = $data;
                }
            }
        }
        return array_unique($customDatas, SORT_REGULAR);
    }
}