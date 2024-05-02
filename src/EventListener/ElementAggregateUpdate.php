<?php

namespace App\EventListener;

use App\Document\Element;
use App\Document\ElementStatus;
use App\Services\ElementDuplicatesService;
use Doctrine\ODM\MongoDB\DocumentManager;

class ElementAggregateUpdate
{
    protected $config = null;
    protected $logger;
    protected $duplicateService;

    public function __construct(DocumentManager $dm, ElementDuplicatesService $duplicateService)
    {
        $this->dm = $dm;
        $this->duplicateService = $duplicateService;
    }

    public function getConfig()
    {
        if (!$this->config) {
            $this->config = $this->dm->get('Configuration')->findConfiguration();
        }

        return $this->config;
    }

    public function onFlush(\Doctrine\ODM\MongoDB\Event\OnFlushEventArgs $eventArgs): void
    {
        $dm = $eventArgs->getDocumentManager();
        $uow = $dm->getUnitOfWork();
        
        foreach ($uow->getScheduledDocumentUpdates() as $document) {
            
            if ($document instanceof Element) {
                
                $element = $document;
                $changeSet = $uow->getDocumentChangeSet($document);
                $aggregateToRefresh = null;
                $excludedAggregatedElement = null;

                if ($element->isAggregated()) {
                    $aggregateToRefresh = $element->getAggregate();
                }
                
                if ($element->isAggregate()) {
                    if (isset($changeSet["aggregatedElements"])) {
                        // Reinit status on delete from aggregatdElements list
                        $excludedAggregatedElements = $changeSet["aggregatedElements"][0]->getDeletedDocuments();

                        // handle strange bug, when emptying all aggregatedElement, then the 
                        // getDeletedDocuments do not work and return empty array 
                        if ($changeSet["aggregatedElements"][0]->count() == 0) {
                            $excludedAggregatedElements = $dm->query('Element')
                                                             ->field('aggregate')->references($document)
                                                             ->execute();
                        }
                        forEach ($excludedAggregatedElements as $excludedAggregatedElement) {
                            $excludedAggregatedElement->setStatus(ElementStatus::ModifiedByAdmin);
                            $excludedAggregatedElement->setAggregate(null);
                            $class = $dm->getClassMetadata(get_class($excludedAggregatedElement));
                            try {
                                $dm->getUnitOfWork()->recomputeSingleDocumentChangeSet($class, $excludedAggregatedElement);
                            } catch (\Exception $e) {
                                // when hard deleting one aggregated, it does not exist anymore on DB during the flush
                                // so it raise an error here that we can ignore
                            }
                        }
                        $newAggregatedElements = $changeSet["aggregatedElements"][0]->getInsertedDocuments();
                        forEach ($newAggregatedElements as $newAggregatedElement) {
                            $newAggregatedElement->setStatus(ElementStatus::Aggregated);
                            $newAggregatedElement->setAggregate($element);
                            $class = $dm->getClassMetadata(get_class($newAggregatedElement));
                            $dm->getUnitOfWork()->recomputeSingleDocumentChangeSet($class, $newAggregatedElement);
                        }
                    }
                    $aggregateToRefresh = $element;
                }
                
                if ( ! is_null($aggregateToRefresh)) {
                    $this->duplicateService->refreshAggregate($aggregateToRefresh);
                    $class = $dm->getClassMetadata(get_class($aggregateToRefresh));
                    $dm->getUnitOfWork()->recomputeSingleDocumentChangeSet($class, $aggregateToRefresh);
                }                
            }
        }
    }
}
