<?php

namespace App\Services;

use App\Document\Coordinates;
use App\Document\Element;
use App\Document\Import;
use App\Document\UserInteractionContribution;
use GuzzleHttp\Promise\Promise;
use Services_OpenStreetMap;
use GuzzleHttp\Psr7\Response;
use Doctrine\ODM\MongoDB\DocumentManager;
use Symfony\Contracts\Translation\TranslatorInterface;

class ElementSynchronizationService
{
    protected $config;

    const MAIN_OSM_KEYS = ['highway', 'natural', 'landuse', 'power', 'waterway', 'amenity', 'barrier', 'place', 'leisure', 'railway', 'shop', 'man_made', 'public_transport', 'tourism', 'boundary', 'emergency', 'historic', 'type', 'traffic_sign', 'office', 'traffic_calming', 'aeroway', 'healthcare', 'aerialway', 'craft', 'geological', 'military', 'telecom'];
    const MAIN_OSM_KEYS_FALLBACK = ['addr:housenumber', 'entrance', 'information', 'indoor', 'building']; // To check only if no main OSM tags has been found, as they can be used as descriptive tags and not only main tags
    const EARTH_RADIUS = 6378;
    const OSM_SEARCH_RADIUS_METERS = 50;

    public function __construct(DocumentManager $dm, UrlService $urlService, $appVersion, TranslatorInterface $t)
    {
        $this->dm = $dm;
        $this->urlService = $urlService;
        $this->appVersion = $appVersion;
        $this->t = $t;
    }

    private function trans($key, $params = [])
    {
        return $this->t->trans($key, $params, 'admin');
    }

    public function getConfig()
    {
        if (!$this->config) $this->config = $this->dm->get('Configuration')->findConfiguration();
        return $this->config;
    }

    /*
        Dispatch the contribution on the original database
        Log in into OSM
        Commit the change...
    */
    public function asyncDispatchToOSM(Element $element, $action, $feature = null) : Promise
    {
        // Wrap the whole function into a promise to make it asynchronous
        $promise = new Promise(function () use (&$promise, &$element, &$action, &$feature) {
            try {
                // Init OSM API handler
                $osm = $this->getOsmApiHandler();
                $gogoFeature = $feature ? $feature : $this->elementToOsm($element);
                $gogoFeaturesMainTags = $this->getMainTags($gogoFeature);

                if ($action == 'add' && $gogoFeature['type']) {
                    // It mean a duplicate on OSM have been found while adding the new element
                    // So for gogocarto is an "Add", but for OSM it will be an "update"
                    $action == 'edit';
                }

                if ($action == 'edit' && !$gogoFeature['type']) {
                    return $promise->resolve(new Response(500, [], null, '1.1', $this->trans('config_osm.sync.no_type', [], 'admin') ));
                }
                if ($action == 'add' && count($gogoFeaturesMainTags) == 0) {
                    return $promise->resolve(new Response(500, [], null, '1.1', $this->trans('config_osm.sync.notags', [], 'admin') ));
                }

                if ($action == 'delete') {
                    return $promise->resolve(new Response(200, [], null, '1.1',
                                             $this->trans('config_osm.sync.deletion_not_allowed', [], 'admin')));
                }

                $toAdd = null;

                // Process contribution
                // New feature
                if ($action == 'add') {
                    $toAdd = $osm->createNode($gogoFeature['center']['latitude'], $gogoFeature['center']['longitude'], $gogoFeature['tags']);
                }
                // Edit existing feature
                else if ($action == 'edit') {
                    $osmFeature = null;
                    $getType = "get".ucfirst($gogoFeature['type']);
                    try {
                        $osmFeature = $osm->$getType($gogoFeature['osmId']);
                    } catch(\Throwable $e) {}

                    if ($osmFeature) {
                        $localVersion = $element->getCustomProperty('osm_version');
                        // Check version number (to make sure Gogocarto version is the latest)
                        if ($osmFeature->getVersion() == intval($localVersion)) {
                            if ($this->editOsmFeatureWithGoGoFeature($osmFeature, $gogoFeature))
                                $toAdd = $osmFeature;
                            else
                                return $promise->resolve(new Response(200, [], null, '1.1',
                                                            $this->trans('config_osm.sync.nothing_to_update', [], 'admin')));
                        }
                        else {
                            $message = $this->trans('config_osm.sync.version_mismatch', [
                                'local_version' => $localVersion,
                                'remote_version' => $osmFeature->getVersion()
                                ], 'admin');
                            return $promise->resolve(new Response(500, [], null, '1.1', $message));
                        }
                    }
                    else {
                        $message = $this->trans('config_osm.sync.no_feature', [], 'admin');
                        return $promise->resolve(new Response(404, [], null, '1.1', $message));
                    }
                }

                // Create changeset and upload changes
                if (isset($toAdd)) {
                    $changeset = $this->createsChangeSet($osm, $toAdd, $this->getOsmComment($element, $action));

                    // Close changeset
                    try {
                        $changeset->commit();

                        // Update version in case of feature edit
                        $toUpdateInDb = null;

                        if ($action == 'add') {
                            $toUpdateInDb = $osm->getNode($toAdd->getId());
                        }
                        else if ($action == 'edit') {
                            $toUpdateInDb = $osm->$getType($gogoFeature['osmId']);
                        }

                        if ($toUpdateInDb) {
                            if ($action == 'add') {
                                $element->setCustomProperty('osm_type', 'node');
                                $element->setOldId($toUpdateInDb->getId());
                            }
                            $element->setCustomProperty('osm_url', $element->getOsmUrl($this->config));
                            $element->setCustomProperty('osm_version', $toUpdateInDb->getVersion());
                            $element->setCustomProperty('osm_timestamp', strval($toUpdateInDb->getAttributes()->timestamp));
                            $element->setCustomProperty('osm_tags', $toUpdateInDb->getTags());
                            $this->dm->persist($element);
                            $this->dm->flush();
                        }

                        $message = $this->trans('config_osm.sync.success', [], 'admin');
                        return $promise->resolve(new Response(200, [], null, '1.1', $message));
                    }
                    catch(\Exception $e) {
                        $message = 'Error when sending changeset';
                        $code = $e->getCode() != 200 ? $e->getCode() : 500; // Ensure error does not return 200
                        return $promise->resolve(new Response($code, [], null, '1.1', $message));
                    }
                }
            }
            catch(\Exception $e) {
                return $promise->resolve(new Response(500, [], null, '1.1', $e->getMessage()));
            }
        });

        return $promise;
    }

    private function editOsmFeatureWithGoGoFeature($osmFeature, $gogoFeature)
    {
        // Avoid empty commit : in gogocarto the update might be on field that are not sent to OSM
        $isNewFeatureDifferentFromOldOne = false;

        // Edit tags
        $osmTags = $osmFeature->getTags();
        foreach($gogoFeature['tags'] as $tagKey => $gogoTagValue) {
            if (isset($osmTags[$tagKey]) && $osmTags[$tagKey] != $gogoTagValue) $isNewFeatureDifferentFromOldOne = true;
        }

        foreach($gogoFeature['tags'] as $k => $v) {
            if ($v == null || $v == '') {
                $osmFeature->removeTag($k);
            }
            else {
                $osmFeature->setTag($k, $v);
            }
        }

        // If node coordinates are edited, check if it is detached
        if ($gogoFeature['type'] == 'node' && (!$osmFeature->getWays()->valid() || $osmFeature->getWays()->count() == 0)) {
            if ($gogoFeature['center']['latitude'] != $osmFeature->getLat()) {
                $osmFeature->setLat($gogoFeature['center']['latitude']);
                $isNewFeatureDifferentFromOldOne = true;
            }
            if ($gogoFeature['center']['longitude'] != $osmFeature->getLon()) {
                $osmFeature->setLon($gogoFeature['center']['longitude']);
                $isNewFeatureDifferentFromOldOne = true;
            }
        }

        return $isNewFeatureDifferentFromOldOne;
    }

    private function createsChangeSet($osm, $feature, $comment)
    {
        $changeset = $osm->createChangeset();
        $changeset->setId(-1); // To prevent bug with setTag
        $changeset->setTag('host', $this->urlService->generateUrl());
        $changeset->setTag('created_by', "GoGoCarto"); // not including version for now to keep it simple, but in future we cna change that : " v" . $this->appVersion . ' - ' . $this->getConfig()->getAppName());
        $changeset->begin($comment);

        // Add edited feature to changeset
        $changeset->add($feature);

        return $changeset;
    }

    /**
     * Convert an element into a JSON-like OSM feature
     */
    public function elementToOsm(Element $element)
    {
        if (!$element->isSynchedWithExternalDatabase()) return null;
        $gogoFeature = [];
        $originalTags = $element->getProperty('osm_tags');

        $taxonomy = $element->getSource()->getTaxonomyMapping();
        $importOntology = $element->getSource()->getOntologyMapping();
        $ontology = [];
        foreach($importOntology as $key => $value) {
            if ($value['mappedProperty']) {
                $ontology[$value['mappedProperty']][] = $key;
            }
        }
        // handle multiple osm key mapped to the same gogoKey (example addr:street and contact:street
        // being both mapped to streetAddress). We will keep the one originally used by the element on OSM
        foreach($ontology as $key => $values) {
            if (count($values) > 1) {
                $filtered = array_intersect($values, $originalTags ?? []);
                if (count($filtered) > 0) $values = $filtered;
            }
            $ontology[$key] = $values[0];
        }

        // Type
        $gogoFeature['type'] = $element->getProperty('osm_type');

        // Coordinates
        $gogoFeature['center']['latitude'] = $element->getGeo()->getLatitude();
        $gogoFeature['center']['longitude'] = $element->getGeo()->getLongitude();

        // Categories
        foreach($element->getCategoriesIds() as $catId) {
            foreach($taxonomy as $taxonomyKey => $taxonomyValue) {
                if (in_array($catId, $taxonomyValue["mappedCategoryIds"])) {
                    $this->setNestedArrayValue($gogoFeature, "tags/{$taxonomyValue['fieldName']}", $taxonomyValue['inputValue'], "/");
                }
            }
        }
        // Core fields
        $myCoreFields = array_diff($element::CORE_FIELDS, ['latitude', 'longitude', 'categories']);
        foreach($myCoreFields as $field) {
            if (isset($ontology[$field])) {
                $elemValue = $element->getProperty($field);
                $this->setNestedArrayValue($gogoFeature, $ontology[$field], $elemValue, "/");
            }
        }

        // Openhours
        if (array_key_exists('osm_opening_hours', $ontology) && $element->getOpenHours()) {
            $elemValue = $element->getOpenHours()->toOsm();
            $this->setNestedArrayValue($gogoFeature, $ontology['osm_opening_hours'], $elemValue, "/");
        }

        // Custom data
        foreach($element->getData() as $elemKey => $elemValue) {
            if (!str_starts_with($elemKey, 'osm_') && isset($ontology[$elemKey])) {
                $this->setNestedArrayValue($gogoFeature, $ontology[$elemKey], $elemValue, "/");
            }
        }

        // Other data
        $gogoFeature['osmId'] = intval($element->getProperty('oldId'));

        // Tags from the import query
        $queries = $element->getSource()->getOsmQueries();
        if (count($queries) == 1) {
            $query = $queries[0];
            foreach($query as $condition) {
                if ($condition->operator == "=" && !isset($gogoFeature['tags'][$condition->key]))
                    $gogoFeature['tags'][$condition->key] = $condition->value;
            }
        }

        // remove streetNumber from streetAddress (before 2022 in gogocarto there was no streetNumber)
        foreach(['addr:', 'contact:'] as $prefix) {
            if (isset($gogoFeature['tags']["{$prefix}street"]) && empty($gogoFeature['tags']["{$prefix}housenumber"])) {
                $pattern = "/^([0-9]+\s).*/";
                preg_match_all($pattern, $gogoFeature['tags']["{$prefix}street"], $matches);
                if (count($matches[1]) > 0) {
                    $gogoFeature['tags']["{$prefix}street"] = ltrim($gogoFeature['tags']["{$prefix}street"], $matches[1][0]);
                    $gogoFeature['tags']["{$prefix}housenumber"] = $matches[1][0];
                }
            }
        }

        // trim all values
        $gogoFeature['tags'] = array_map(function($value) {
            return trim($value);
        }, $gogoFeature['tags']);

        // remove empty key values
        $gogoFeature['tags'] = array_filter($gogoFeature['tags']);

        // execute custom code
        try {
            eval(str_replace('<?php', '', $element->getSource()->getCustomCodeForExport()));
        } catch (\Exception $e) {
            $gogoFeature['error'] = $e;
        }

        return $gogoFeature;
    }

    /**
     * When adding an element in GoGoCarto, we should consider if this element
     * might be added to OSM as well. If it should, then we detect duplicate on OSM
     * before adding it
     *
     * @param Element $element
     * @return void
     */
    public function checkIfNewElementShouldBeAddedToOsm(Element $element)
    {
        $linkedImport = $element->linkableImport($this->dm);

        if ($linkedImport) {
            $element->setSource($linkedImport);
            // Get element in OSM format
            $gogoFeature = $this->elementToOsm($element);
            $gogoFeaturesMainTags = $this->getMainTags($gogoFeature);

            $duplicates = [];

            // Compute bounding box to retrieve
            $radiusKm = self::OSM_SEARCH_RADIUS_METERS / 1000;
            $north = $gogoFeature['center']['latitude'] + ($radiusKm / self::EARTH_RADIUS) * (180 / M_PI);
            $east = $gogoFeature['center']['longitude'] + ($radiusKm / self::EARTH_RADIUS) * (180 / M_PI) / cos($gogoFeature['center']['latitude'] * M_PI / 180);
            $south = $gogoFeature['center']['latitude'] - ($radiusKm / self::EARTH_RADIUS) * (180 / M_PI);
            $west = $gogoFeature['center']['longitude'] - ($radiusKm / self::EARTH_RADIUS) * (180 / M_PI) / cos($gogoFeature['center']['latitude'] * M_PI / 180);

            // Load data from OSM editing API
            $osm = $this->getOsmApiHandler();
            try {
                $osm->get($west, $south, $east, $north);
                $potentialDuplicates = $osm->search($gogoFeaturesMainTags);
            } catch (\Exception $e) {
                return ['result' => false, 'duplicates' => []];
            }

            // Transform found potential duplicates into GogoCarto format$
            foreach($potentialDuplicates as $dup) {
                $dupOsmId = $dup->getType().'/'.$dup->getId();
                array_push($duplicates, [
                    'name' => $dup->getTag('name') ?? $dup->getTag('brand') ?? $dup->getTag('operator') ?? $dup->getTag('owner') ?? $dup->getTag('ref') ?? $dupOsmId,
                    'osmId' => $dupOsmId,
                    'description' => $this->osmTagsToString($dup->getTags()),
                    'address' => [
                        'streetNumber' => $dup->getTag('addr:housenumber'),
                        'streetAddress' => $dup->getTag('addr:street'),
                        'postalCode' => $dup->getTag('addr:postcode'),
                        'addressLocality' => $dup->getTag('addr:city')
                    ]
                ]);
            }

            return ['result' => true, 'duplicates' => $duplicates];
        } else {
            return ['result' => false, 'duplicates' => []];
        }
    }

    /**
     * Extract the mains tags from the osm feature
     */
    public function getMainTags($osmFeature)
    {
        // List tags to use for potential duplicates search
        $osmFeaturesMainTags = array_filter(
            $osmFeature['tags'],
            function($key) {
                return in_array($key, self::MAIN_OSM_KEYS);
            },
            ARRAY_FILTER_USE_KEY
        );

        if (count($osmFeaturesMainTags) == 0) {
            $osmFeaturesMainTags = array_filter(
                $osmFeature['tags'],
                function($key) {
                    return in_array($key, self::MAIN_OSM_KEYS_FALLBACK);
                },
                ARRAY_FILTER_USE_KEY
            );
        }

        return $osmFeaturesMainTags;
    }

    /**
     * When adding an element that should be linked to OSM, if we detect some
     * duplicates on OSM we show them to the user. If the user click "yes this is a duplicate"
     * of an OSM point, then instead of adding a new point to OSM, we should update the existing
     * OSM point with the new data.
     *
     * @param Element $element The new element being added to GoGoCarto
     * @param integer $odmId ID of the duplicate on OSM
     * @return void
     */
    public function linkElementToOsmDuplicate(Element $element, $osmId)
    {
        // Get OSM element
        $osm = $this->getOsmApiHandler();
        $osmIdParts = explode('/', $osmId); // OSM id is as follow : type/ID <=> node/145236545
        $getType = 'get'.ucfirst($osmIdParts[0]);
        $osmFeature = $osm->$getType($osmIdParts[1]);

        if (!$osmFeature) return;

        // Update element OSM attributes
        $element->setOldId($osmIdParts[1]);
        $element->setCustomProperty('osm_type', $osmFeature->getType());
        $element->setCustomProperty('osm_version', $osmFeature->getVersion());
        $element->setCustomProperty('osm_timestamp', strval($osmFeature->getAttributes()->timestamp));
        $element->setCustomProperty('osm_url', $element->getOsmUrl($this->config));
        $element->setCustomProperty('osm_tags', $osmFeature->getTags());

        // We trust OSM for name and geolocation
        if ($osmFeature->getTag('name')) $element->setName($osmFeature->getTag('name'));
        // getLat exists only for Node object
        if (method_exists($osmFeature, 'getLat')) {
            $element->setGeo(new Coordinates($osmFeature->getLat(), $osmFeature->getLon()));
        }
    }

    /*
     * Get a ready-to-use version of OSM API handler (with account/server defined)
     */
    private function getOsmApiHandler()
    {
        $configOsm = $this->getConfig()->getOsm();
        return new Services_OpenStreetMap([
            'server' => $configOsm->getFormattedOsmHost(),
            'user' => $configOsm->getOsmUsername(),
            'password' => $configOsm->getOsmPassword(),
            'User-Agent' => $this->getConfig()->getAppName(),
            'verbose' => true
        ]);
    }

    /**
     * Generate comment for OSM changeset
     */
    private function getOsmComment($element, $action) {
        return $this->trans('config_osm.sync.comment_text', [
            'action' => $this->trans('config_osm.sync.comments.'.$action),
            'name' => $element->getName() ?? ""
        ], 'admin');
    }

    /**
     * Transform a list of OSM tags object into a human-readable string
     */
    private function osmTagsToString($tags) {
        $str = '';

        foreach($tags as $k => $v) {
            if (strlen($str) > 0) { $str .= ', '; }
            $str .= $k.' = '.$v;
        }

        return $str;
    }

    /**
     * Sets a value in a nested array based on path
     * @param array $array The array to modify
     * @param string $path The path in the array
     * @param mixed $value The value to set
     * @param string $delimiter The separator for the path
     * @return The previous value
     */
    private function setNestedArrayValue(&$array, $path, &$value, $delimiter = '/') {
        if ($value == null || $value == '') return; // prevent empty tags

        $pathParts = explode($delimiter, $path);

        $current = &$array;
        foreach($pathParts as $key) {
            $current = &$current[$key];
        }

        $backup = $current;
        $current = $value;

        return $backup;
    }
    
    public function asyncDispatchToGogocarto(Element $element, $action, $feature = null) : Promise
    {
        // Wrap the whole function into a promise to make it asynchronous
        $promise = new Promise(function () use (&$promise, &$element, &$action, &$feature) {
            
            if (!$element || !$element->isSynchedWithExternalDatabase() || !$element->isFromGogocarto()) {
                return $promise->resolve(new Response(500, [], null, '1.1', 'incorrect call to asyncDispatchToGogocarto'));;
            }

            if ($action == 'delete') {
                return $promise->resolve(new Response(200, [], null, '1.1',
                    $this->trans('config_sync_gogocarto.sync.deletion_not_allowed', [], 'admin')));
            }
            
            if ($action == 'add') {
                return $promise->resolve(new Response(200, [], null, '1.1',
                    $this->trans('config_sync_gogocarto.sync.adding_not_allowed', [], 'admin')));
            }
        
            $import = $element->getSource();
            
            $gogoFeature = $this->elementToGogocarto($element);
            $externalOperator = $this->urlService->generateUrl('gogo_homepage');
            $externalOperator = str_replace('/index.php', '', $externalOperator);
            if (str_ends_with($externalOperator, '/')) {
                $externalOperator = rtrim($externalOperator, '/');
            }

            try {
                
                $url = $import->getGoGoCartoBaseUrl() . "/api/elements/{$element->getOldId()}/edit";
                
                $streamContextOptions = array(
                    'http' => array(
                        'header' => 'Authorization: Basic ' . " \r\n". 
                                    'Content-Type: application/json; charset=UTF-8'. "\r\n",
                        'method'  => 'PUT',
                        'timeout' => 30,
                        'content' => json_encode([
                            'externalOperator' => $externalOperator,
                            'apiKey' => $import->getApiKey(),
                            'gogoFeature' => $gogoFeature,
                        ])
                    )
                );

                $response = @file_get_contents($url, false, getStreamContextOptions($streamContextOptions));
                $httpStatusCode = intval(explode(' ', $http_response_header[0])[1]);
                
                if (!$response || $httpStatusCode !== 200) { 
                    return $promise->resolve(new Response($httpStatusCode, [], null, '1.1', ''));
                }
                
                $json = json_decode($response, true);
                return $promise->resolve(new Response(200, [], null, '1.1', $json['message'] ?? null));

            }
            catch(\Exception $e) {
                return $promise->resolve(new Response(500, [], null, '1.1', $e->getMessage()));
            }
        });

        return $promise;
    }
    
    /**
     * Convert an element to be send to another gogocarto project
     */
    public function elementToGogocarto(Element $element)
    {
        if (!$element->isSynchedWithExternalDatabase()) return null;
        
        $import = $element->getSource();
        
        $gogoFeature = [
            'data' => [],
            'mappedCategories' => [],
        ];
        
        $editableCoreFields = [
            'name',
            'images',
            'files',
            'latitude',
            'longitude',
            'streetNumber',
            'streetAddress',
            'addressLocality',
            'postalCode',
            'addressCountry',
            'customFormatedAddress',
            'openHours'
        ];
        $editableFields = array_merge($editableCoreFields, $this->dm->get('Element')->findDataCustomProperties());
        $importOntology = $import->getOntologyMapping();
        $uniqueMappedValues = [];
        foreach($importOntology as $key => $value) {
            if ($value['mappedProperty']) {
                if (in_array($value['mappedProperty'], $editableFields)) {
                    // when source field is used to filled several local fields,
                    // value is only send back from the first local field in mapping
                    if (!in_array($value['mappedProperty'], $uniqueMappedValues)) {
                        $uniqueMappedValues[$key] = $value['mappedProperty'];
                        $elementValue = $element->getProperty($value['mappedProperty']);
                        if ($value['mappedProperty'] === 'openHours' && $elementValue) {
                            $gogoFeature['data'][$key] = $elementValue->toJson();
                        } else {
                            $gogoFeature['data'][$key] = $elementValue;
                        }
                    }
                }
            }
        }
        
        // form fields of type "link between items"
        $config = $this->dm->get('Configuration')->findConfiguration();
        foreach($config->getElementFormFields() as $field) {
            if ($field->type === 'elements') {
                $sourceFieldName = array_search($field->name, $uniqueMappedValues);
                $elementsLinkedFields = [];
                if ($sourceFieldName && array_key_exists($sourceFieldName, $gogoFeature['data'])) {
                    if  ($gogoFeature['data'][$sourceFieldName] && is_iterable($gogoFeature['data'][$sourceFieldName])) {
                        foreach($gogoFeature['data'][$sourceFieldName] as $key => $value) {
                            $linkedElement = $this->dm->get('Element')->find(strval($key));
                            if (
                                $linkedElement
                                && $linkedElement->getOldId()
                                && $linkedElement->getSource()
                                && $import->getSourceName() === $linkedElement->getSource()->getSourceName()
                            ) {
                                $elementsLinkedFields[$linkedElement->getOldId()] = $linkedElement->getName();
                            }
                        }
                        $gogoFeature['data'][$sourceFieldName] = $elementsLinkedFields;
                    }
                }
            }
        }
        
        $taxonomyMapping = $import->getTaxonomyMapping();
        $gogoFeature['mappedCategories'] = [];
        foreach($taxonomyMapping as $key => $value) {
            foreach($value['mappedCategoryIds'] as $categoryId) {
                $gogoFeature['mappedCategories'][$value['inputId']] = false;
                if (in_array($categoryId, $element->getOptionIds())) {
                    $gogoFeature['mappedCategories'][$value['inputId']] = true;
                }
            }
        }
        
        // execute custom code
        try {
            eval(str_replace('<?php', '', $import->getCustomCodeForExport()));
        } catch (\Exception $e) {
            $gogoFeature['error'] = $e;
        }
        
        return $gogoFeature;
    }
}
