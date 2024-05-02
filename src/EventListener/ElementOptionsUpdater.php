<?php

namespace App\EventListener;

use App\Document\Element;
use Doctrine\ODM\MongoDB\DocumentManager;

class ElementOptionsUpdater
{
    public function __construct(DocumentManager $dm, ElementJsonGenerator $elementJsonService)
    {
        $this->dm = $dm;
        $this->config = $this->dm->get('Configuration')->findConfiguration();
        $this->elementJsonService = $elementJsonService;
        $this->optionIds=[];
    }
    
    public function onFlush(\Doctrine\ODM\MongoDB\Event\OnFlushEventArgs $eventArgs): void
    {
        $uow = $this->dm->getUnitOfWork();
        
        foreach ($uow->getScheduledDocumentInsertions() as $document) {
            
            if ($document instanceof Element) {
                
                $element = $document;
                $this->optionIds = $element->getOptionIds();
                $newOptionValues = $element->getOptionValues();
                foreach ($newOptionValues as $optionValue) {
                    $optionId = $optionValue->getOptionId();
                    $option = $this->dm->get('Option')->find($optionId);
                    $this->recursivlyAddParentOptions($element, $option);
                }
                $element->setOptionIds($this->optionIds);
                $this->elementJsonService->updateJsonRepresentation($element);
                $uow->computeChangeSet($this->dm->getClassMetadata(get_class($element)), $element);
            }
        }
        
        foreach ($uow->getScheduledDocumentUpdates() as $document) {
            
            if ($document instanceof Element) {
                
                $element = $document;
                $newOptionValues = $element->getOptionValues();
                if (!$newOptionValues) return;
                $ov_insertDiff = $newOptionValues->getInsertDiff();
                $ov_deleteDiff = $newOptionValues->getDeleteDiff();
                
                if (count($ov_insertDiff) === 0 && count($ov_deleteDiff) ===0) {
                    return;
                } else {
                    $ov_snapshot = $newOptionValues->getSnapshot();
                    $ov_snapshot_ids = [];
                    foreach ($ov_snapshot as $ov) {
                        $ov_snapshot_ids[] = $ov->optionId;
                    }
                    $this->optionIds = $element->getOptionIds();
                    
                    // inserted optionValues
                    foreach ($ov_insertDiff as $optionValue) {
                        $optionId = $optionValue->getOptionId();
                        $option = $this->dm->get('Option')->find($optionId);
                        $this->recursivlyAddParentOptions($element, $option);
                    }
                    
                    // deleted optionValues
                    foreach ($ov_deleteDiff as $optionValue) {
                        $optionId = $optionValue->getOptionId();
                        $option = $this->dm->get('Option')->find($optionId);
                        $this->removeChildrenOptions($option);
                    }
                    
                    // final set of optionIds
                    $element->setOptionIds($this->optionIds);
                    $this->elementJsonService->updateJsonRepresentation($element);
                    $uow->computeChangeSet($this->dm->getClassMetadata(get_class($element)), $element);
                }
            }
        }
    }
    
    private function recursivlyAddParentOptions($element, $option) {
        $parentOption = $option->getParentOption();
        if (!$parentOption) {
            return;
        }
        if ($this->isOptionAlreadyLinked($parentOption)) {
            // nothing to do
        } else {
            $this->optionIds[] = $parentOption->getId();
        }
        $this->recursivlyAddParentOptions($element, $parentOption);
    }
    
    private function isOptionAlreadyLinked($option) {
        if (count($this->optionIds) === 0) {
            return false;
        } else {
            return in_array($option->getId(), $this->optionIds);
        }
    }
    
    private function removeChildrenOptions($option) {
        if ($option) {
            $this->optionIds = array_diff($this->optionIds, $option->getIdAndChildrenOptionIds());
        }
    }
}
