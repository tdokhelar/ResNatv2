<?php

namespace App\Services;

use App\Document\ElementStatus;
use App\Document\GoGoLogImport;
use App\Document\ImportState;
use App\Document\ModerationState;
use Doctrine\ODM\MongoDB\DocumentManager;
use App\Services\Utf8Encoder;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Contracts\Cache\ItemInterface;
class ElementImportService
{
    private $dm;

    protected $countElementCreated = 0;
    protected $countElementUpdated = 0;
    protected $countElementNothingToDo = 0;
    protected $countElementErrors = 0;
    protected $countNoCategoryPreventImport = 0;
    protected $elementIdsErrors = [];
    protected $errorsMessages = [];
    protected $errorsCount = [];
    protected $manuallyStarted = true;

    const BATCH_SIZE = 25;

    /**
     * Constructor.
     */
    public function __construct(DocumentManager $dm, ElementImportOneService $importOneService,
                              ElementImportMappingService $mappingService,
                              UserNotificationService $notifService,
                              ElementDuplicatesService $duplicateService,
                              ElementActionService $actionService,
                              TranslatorInterface $t)
    {
        $this->dm = $dm;
        $this->importOneService = $importOneService;
        $this->mappingService = $mappingService;
        $this->notifService = $notifService;
        $this->duplicateService = $duplicateService;
        $this->actionService = $actionService;
        $this->t = $t;
    }

    private function trans($key, $params = [])
    {
        return $this->t->trans($key, $params, 'admin');
    }

    // read the data and extract ontology and categories. After this operation, the user will be able
    // create a mapping table for ontology and taxonomy
    public function collectData($import)
    {
        $data = $this->loadData($import);
        return $this->mappingService->transform($data, $import);
    }

    public function startImport($import, $manuallyStarted = true)
    {
        $this->manuallyStarted = filter_var($manuallyStarted, FILTER_VALIDATE_BOOLEAN);
        $this->countElementCreated = 0;
        $this->countElementUpdated = 0;
        $this->countElementNothingToDo = 0;
        $this->countElementErrors = 0;
        $this->countNoCategoryPreventImport = 0;
        $this->elementIdsErrors = [];
        $this->errorsMessages = [];
        $this->errorsCount = [];

        $import->setCurrState(ImportState::Downloading);
        $import->setCurrMessage($this->trans('importService.inProgress'));
        if ($import->isDynamicImport()) $import->updateNextRefreshDate();
        $this->dm->persist($import);
        $this->dm->flush();

        $data = $this->loadData($import);
        return $this->importData($data, $import);
    }

    private function loadData($import)
    {
        if ($import->getUrl()) $data = $this->loadJson($import);
        elseif ($import->getFilePath()) $data = $this->loadCsv($import);
        if (!isset($data)) $this->throwEmptyData($import);

        return $data;
    }

    private function throwEmptyData($import) {
        $msg = $this->trans('imports.controller.sonata.error.main') . ' ';
        if ($import->getSourceType() == 'csv') $msg .= $this->trans('imports.controller.sonata.error.csv');
        else $msg .= $this->trans('imports.controller.sonata.error.json');
        throw new \Exception($msg);
    }

    /**
     * Load CSV
     */
    private function loadCsv($import)
    {
        list($header,$data) = $this->readCSV($import->getFilePath(), ',');
        if ($header && count($header) < 3) {
            // try with ; separtor
            list($header,$data) = $this->readCSV($import->getFilePath(), ';');
        }
        return $data;
    }

    private function readCSv($fileName, $delimiter)
    {
        $data = []; $header = null;
        if (false !== ($handle = fopen($fileName, 'r'))) {
            while (false !== ($row = fgetcsv($handle, 0, $delimiter))) {
                $row = Utf8Encoder::toUTF8($row);
                if (!$header) 
                    $header = $row;
                else if (count($header) == count($row))
                    $data[] = array_combine($header, $row);
            }
            fclose($handle);
        }
        return [$header, $data];
    }

    /**
     * Load JSON
     */
    private function loadJson($import)
    {
        $cache = new FilesystemAdapter();
        $data = $cache->get($import->getJSONCacheKey(), function (ItemInterface $item) use ($import) {
            $item->expiresAfter(3600);
            $import->setJsonCachedAt(new \DateTime());

            $streamContextOptions = ['http' => ['timeout' => 3600]];
            $json = file_get_contents(str_replace(' ', '%20', $import->getUrl()), 0,  getStreamContextOptions($streamContextOptions));
            $json = str_replace(chr(10), '', $json); // fix broken json due to non escaped linebreaks
            return json_decode($json, true);
        });

        return $data;
    }

    /**
     * The real method to import. It go through each data provided (read from CSV or Json)
     * And create/delete elements in gogocarto
     */
    private function importData($data, $import)
    {
        if (!$data) return 0;
        try {
            // -----------------------------------
            // Transform the data before importing
            // -----------------------------------
            $data = $this->mappingService->transform($data, $import);
            $import->setCurrState(ImportState::InProgress);
            
            // --------------
            // Prepare Import
            // --------------            
            $startImportTimestamp = time();
            $previouslyImportedElementIds = [];
            $newlyImportedElementIds = [];
            $createdElementIds = [];

            // before updating the source, we collect all elements ids
            $previouslyImportedElementIds = $this->dm->query('Element')
                ->field('source')->references($import)->getIds();
            $previouslyImportedElementIds = array_map(function($id) {
                return strval($id); // fix some id are just numbers
            }, $previouslyImportedElementIds);
            $this->importOneService->initialize($import);

            // -----------------
            // Process each data
            // -----------------
            $i = 0; $size = count($data);
            foreach ($data as $row) {
                try {
                    $import->setCurrMessage($this->trans('importService.processing', ['%i%'=>$i, '%size%'=>$size]));
                    $result = $this->importOneService->createElementFromArray($row, $import);
                    if (isset($result['id']))
                        $newlyImportedElementIds[] = $result['id'];
                    // saving modified version so it does not get deleted at the end of the import
                    if (isset($result['id_pending_modified'])) 
                        $newlyImportedElementIds[] = $result['id_pending_modified'];
                    
                    switch ($result['status']) {
                      case 'nothing_to_do': $this->countElementNothingToDo++; break;
                      case 'created': $this->countElementCreated++; $createdElementIds[] = $result['id']; break;
                      case 'updated': $this->countElementUpdated++; break;
                      case 'no_category': $this->countNoCategoryPreventImport++; break;
                    }
                    ++$i;
                } catch (\Exception $e) {
                    ++$this->countElementErrors;
                    if (isset($row['id']) && !is_array($row['id'])) {
                        $this->elementIdsErrors[] = ''.$row['id'];
                        $newlyImportedElementIds[] = ''.$row['id'];
                    }
                    $msgCode = $e->getMessage() && strlen($e->getMessage()) > 0 ? $e->getMessage() : 'error'; 
                    if (!array_key_exists($msgCode, $this->errorsCount)) {
                        $this->errorsCount[$msgCode] = 1;
                    } else {
                        ++$this->errorsCount[$msgCode];
                    }
                    $message = '<u>'.$e->getMessage().'</u> <b>(x'.$this->errorsCount[$msgCode].')</b><br/>'.$e->getFile().' LINE '.$e->getLine().'<br/>';
                    $message .= 'CONTEXT : <pre>'.print_r($row, true).'</pre>';
                    $this->errorsMessages[$msgCode] = $message;
                }

                if (0 === ($i % self::BATCH_SIZE)) $import = $this->batchFlush($import);
            }
            $import = $this->batchFlush($import);

            // ----------------------------------
            // Remove Elements no longer imported
            // ----------------------------------
            $countElementDeleted = 0;
            $elementIdsToDelete = array_diff($previouslyImportedElementIds, $newlyImportedElementIds);

            // first get proper count
            $elementsToDeleteQuery = $this->dm->query('Element')
                ->field('status')->gt(ElementStatus::AdminRefused)
                ->field('source')->references($import)            
                ->field('id')->in($elementIdsToDelete);
            $countElementDeleted = (clone $elementsToDeleteQuery)->getCount();

            if ($import->getModerateElements()) {
                // do not delete but put moderate status "to be deleted"
                $elementsToDeleteQuery->updateMany()
                    ->field('moderationState')->set(ModerationState::DeletedBySource)
                    ->execute();
            } else if ($import->getIsSynchronized()) {
                // soft delete, so if there is a problem local changes are not lost
                $message = $this->trans("dynamic_imports.messages.deleted_by_source");
                foreach($elementsToDeleteQuery->execute() as $element) {
                    $this->actionService->delete($element, false, $message);
                }
            } else {
                // delete permanently
                $elementsToDeleteQuery->batchRemove();
                $this->dm->persist($import); // batch remove call a dm->clear so need to eprsist again
            }

            // ---------------------------------
            // Link elements between each others
            // ---------------------------------
            $config = $this->dm->get('Configuration')->findConfiguration();
            $elementsLinkedFields = [];
            foreach($config->getElementFormFields() as $field) {
                if ($field->type === 'elements' && in_array($field->name, $import->getMappedProperties())) {
                    $elementsLinkedFields[] = $field->name;
                    // resetting the reversed field to it will be filled correctly
                    if (isset($field->reversedBy) && $field->reversedBy !== $field->name) {
                        $qb = $this->dm->query('Element');
                        $qb->field('source')->references($import)
                           ->updateMany()
                           ->field("data.$field->reversedBy")->unsetField()
                           ->execute();
                    }
                }
            }
            $this->dm->flush(); // execute the unsetField queries
            // Go through each individual imported elements, and link elements from each other
            if (count($elementsLinkedFields) > 0) {                
                $importedElements = $this->dm->query('Element')
                    ->field('source')->references($import)
                    ->execute();
                $i = 0; $size = count($importedElements);
                foreach ($importedElements as $element) {
                    $elementJsonUpdateNeeded = false;
                    foreach ($elementsLinkedFields as $linkField) {
                        $import->setCurrMessage($this->trans('importService.x', [
                            'linkField' => $linkField,
                            'i' => $i,
                            'size' => $size
                        ], 'admin'));
                        $values = $element->getCustomProperty($linkField);
                        if ($values !== null) {
                            if (!is_array($values)) $values = preg_split("/[,;]/", $values);
                            $values = array_map('trim', $values);
                            $values = array_filter($values, function($e) { return strlen($e) > 0; });
                            if (count($values) > 0) {
                                $qb = $this->dm->query('Element');
                                $qb->field('source')->references($import);
                                $qb->addOr($qb->expr()->field('name')->in($values));
                                // When importing from GoGoCarto, values are stored { id: Name, "kbA": "Some Element Name" }
                                // But we also handle importing from standard array [ id_or_name, "kbA", "Name of other element"]
                                $ids = is_associative_array($values) ? array_keys($values) : $values;
                                $qb->addOr($qb->expr()->field('oldId')->in($ids));
                                $result = $qb->select('name')->getArray();
                                $element->setCustomProperty($linkField, $result);
                                $elementJsonUpdateNeeded = true;
                            }
                        }
                    }
                    if ($elementJsonUpdateNeeded) {
                        $this->dm->persist($element);
                    }
                    if (0 === (++$i % self::BATCH_SIZE)) $import = $this->batchFlush($import);
                }
                $import = $this->batchFlush($import);
            }

            // -----------------
            // Detect Duplicates
            // -----------------
            $automaticMergesCount = 0;
            $potentialDuplicatesCount = 0;
            if ($config->getDuplicates()->getDetectAfterImport() && $createdElementIds > 0) {
                $elements = $this->dm->query('Element')
                    ->field('source')->references($import)
                    ->field('createdAt')->gt(new \MongoDate($startImportTimestamp))
                    ->getCursor();
                $i = 0; $size = $elements->count();
                foreach($elements as $element) {
                    $import->setCurrMessage($this->trans('importService.duplicate', [
                        'i' => $i,
                        'size' => $size
                    ], 'admin'));
                    $result = $this->duplicateService->detectDuplicatesFor($element);
                    if ($result && $result['automaticMerge']) ++$automaticMergesCount;
                    if ($result && !$result['automaticMerge']) ++$potentialDuplicatesCount;
                    if (0 === (++$i % self::BATCH_SIZE)) $import = $this->batchFlush($import);
                }
                $import = $this->batchFlush($import);
            }            

            // -----------
            // Create Logs
            // -----------
            $totalCount = $this->dm->query('Element')
                ->field('status')->notEqual(ElementStatus::ModifiedPendingVersion)
                ->field('source')->references($import)
                ->getCount();
            $elementsMissingGeoCount = $this->dm->query('Element')
                ->field('source')->references($import)
                ->field('moderationState')->equals(ModerationState::GeolocError)
                ->getCount();
            $elementsMissingTaxoCount = $this->dm->query('Element')
                ->field('source')->references($import)
                ->field('moderationState')->equals(ModerationState::NoOptionProvided)
                ->getCount();
            $logData = [
                'elementsCount' => $totalCount,
                'elementsCreatedCount' => $this->countElementCreated,
                'elementsUpdatedCount' => $this->countElementUpdated,
                'elementsNothingToDoCount' => $this->countElementNothingToDo,
                'elementsMissingGeoCount' => $elementsMissingGeoCount,
                'elementsMissingTaxoCount' => $elementsMissingTaxoCount,
                'elementsPreventImportedNoTaxo' => $this->countNoCategoryPreventImport,
                'elementsDeletedCount' => $countElementDeleted,
                'elementsErrorsCount' => $this->countElementErrors,
                'automaticMergesCount' => $automaticMergesCount,
                'potentialDuplicatesCount' => $potentialDuplicatesCount,
                'errorMessages' => $this->errorsMessages,
            ];
            $totalErrors = $elementsMissingGeoCount + $elementsMissingTaxoCount + $this->countElementErrors;
            $logLevel = $totalErrors > 0 ? ($totalErrors > ($size / 4) ? 'error' : 'warning') : 'success';
            $message = $this->trans('importService.logMessage', ['%name%' => $import->getSourceName()]);
            if ('success' != $logLevel) {
                $message .= $this->trans('importService.logError');
            }
            $log = new GoGoLogImport($logLevel, $message, $logData);
            $this->dm->persist($log);
            $this->dm->flush(); // flush the log before the import, because some time the log save fails but not the import which leads in broken relation
            $import->addLog($log);
            $import->setCurrState($totalErrors > 0 ? ($totalErrors == $size ? ImportState::Failed : ImportState::Errors) : ImportState::Completed);
            $import->setCurrMessage($log->displayMessage($this->t));
            $import->setLastRefresh(time());
            
            // ------------
            // Notify Users
            // ------------
            if ($import->isDynamicImport() && !$this->manuallyStarted) {
                if ($totalErrors > 0) {
                    $this->notifService->notifyImportError($import);
                }
                if ($import->getNewOntologyToMap() || $import->getNewTaxonomyToMap()) {
                    $this->notifService->notifyImportMapping($import);
                }
            }

            $this->dm->flush();
        } catch (\Error $e) {
            $message = '<u>'.$e->getMessage().'</u><br/>'.$e->getFile().' LINE '.$e->getLine().'<br/>';
            $import->setCurrMessage($message);
            $import->setCurrState(ImportState::Errors);
            $this->dm->flush();
        }

        return $message;
    }

    private function batchFlush($import) {
        $this->dm->flush();
        $this->dm->clear();
        // After dm->clear, we need to get again the import from the DB to avoid doctrine raising errors
        $import = $this->dm->get('Import')->find($import->getId());
        $this->dm->persist($import);
        return $import;
    }
}
