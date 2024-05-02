<?php

namespace App\Services;

use App\Document\Element;
use Doctrine\ODM\MongoDB\DocumentManager;
use App\Services\Utf8Encoder;

class ElementImportMappingOntologyService
{
    protected $collectedProps;
    protected $existingProps;
    protected $mappedCoreFields = [
        'title' => 'name', 'nom' => 'name', 'titre' => 'name',
        'mail' => 'email',
        'taxonomy' => 'categories',
        'addr:housenumber' => 'streetNumber', 'contact:housenumber' => 'streetNumber',
        'address' => 'streetAddress', 'addr:street' => 'streetAddress', 'contact:street' => 'streetAddress',
        'city' => 'addressLocality', 'addr:city' => 'addressLocality', 'contact:city' => 'addressLocality',
        'postcode' => 'postalCode', 'addr:postcode' => 'postalCode', 'contact:postcode' => 'postalCode',
        'country' => 'addressCountry', 'addr:country' => 'addressCountry', 'contact:country' => 'addressCountry',
        'lat' => 'latitude',
        'long' => 'longitude', 'lng' => 'longitude', 'lon' => 'longitude',
    ];

    public function __construct(DocumentManager $dm)
    {
        $this->dm = $dm;
    }

    public function collectOntology($data, $import)
    {
        $this->ontologyMapping = $import->getOntologyMapping();
        // reset count & values
        foreach($this->ontologyMapping as &$mappedObject) {
            $mappedObject['collectedCount'] = 0;
            $mappedObject['collectedValues'] = [];
        }
        unset($mappedObject); // prevent edge case https://www.php.net/manual/fr/control-structures.foreach.php

        $props = $this->dm->get('Element')->findAllCustomProperties();
        $props = array_merge(Element::CORE_FIELDS, $props);
        $this->existingProps = [];
        foreach($props as $prop) {
            $this->existingProps[slugify($prop)] = $prop;
        }
        $this->collectedProps = [];
        foreach ($data as $row) {
            foreach ($row as $prop => $value) {
                $this->collectProperty($prop, null, $import, $value);
                if (is_associative_array($value) && !in_array($prop, ['openHours', 'modifiedElement', 'osm_tags'])) {
                    foreach ($value as $subprop => $subvalue) {
                        $this->collectProperty($subprop, $prop, $import, $subvalue);
                    }
                }
            }
        }
        // delete no more used fields
        foreach ($this->ontologyMapping as $prop => $mappedObject) {
            if (!in_array($prop, $this->collectedProps)) {
                unset($this->ontologyMapping[$prop]);
            }
        }
        $count = count($data);
        foreach($this->ontologyMapping as &$mappedObject) {
            $mappedObject['collectedPercent'] = $mappedObject['collectedCount'] / $count * 100;
        }
        $import->setOntologyMapping($this->ontologyMapping);
    }

    private function fixPropName($prop)
    {
        return str_replace('.', '', $prop); // dots are not allawed in MongoDB hash keys
    }

    // Collect an original property of the data imported
    private function collectProperty($prop, $parentProp = null, $import, $value)
    {
        if (in_array($prop, ['__initializer__', '__cloner__', '__isInitialized__'])) {
            return;
        }
        $prop = $this->fixPropName($prop);
        $parentProp = $this->fixPropName($parentProp);
        $fullProp = $parentProp ? $parentProp.'/'.$prop : $prop;
        
        $mappedProp = '';

        if (!$prop || 0 == strlen($prop)) {
            return;
        }

        if (!in_array($fullProp, $this->collectedProps)) {
            $this->collectedProps[] = $fullProp;
        }
        
        // handle some special cases
        if ($import->getSourceType() == 'osm') {
            // auto map all osm attributes
            switch ($prop) {
                case 'opening_hours': $mappedProp = 'osm_opening_hours'; break;
                case 'version': $mappedProp = 'osm_version'; break;
                case 'timestamp': $mappedProp = 'osm_timestamp'; break;
                case 'type': $mappedProp = 'osm_type'; break;
                case 'osm_url': $mappedProp = 'osm_url'; break;
                case 'osm_tags': $mappedProp = 'osm_tags'; break;
                case 'lat': $mappedProp = 'latitude'; break;
                case 'lon': $mappedProp = 'longitude'; break;
            }
        }
        
        if (!array_key_exists($fullProp, $this->ontologyMapping)) {
            // if prop with same name exist in the DB, map it directly to itself
            $slugProp = strtolower(preg_replace('~(^bf_|_)~', '', $prop));
            if (!$mappedProp) $mappedProp = $this->existingProps[$slugProp] ?? '';
            // use alternative name, like lat instead of latitude
            if (!$mappedProp && array_key_exists($slugProp, $this->mappedCoreFields)) {
                $mappedProp = $this->mappedCoreFields[$slugProp];
            }
            if (!$mappedProp && startsWith($slugProp, 'category')) {
                $mappedProp = 'categories';
            }
            // prevent auto mapping for some props
            if (in_array($fullProp, ['tags/source'])) $mappedProp = '';

            // Asign mapping
            $this->ontologyMapping[$fullProp] = [
                'mappedProperty' => $mappedProp,
                'collectedCount' => 1,
                'collectedValues' => [ $this->shortenValue($value) ]
            ];
            $import->setNewOntologyToMap(true);
        } else {
            // update count and values
            $this->ontologyMapping[$fullProp]['collectedCount']++;
            // Do not save more than 10 values for same property
            $valuesCount = count($this->ontologyMapping[$fullProp]['collectedValues']);
            $numberValuesSaved = 10;
            if ($valuesCount < $numberValuesSaved) {
                $this->ontologyMapping[$fullProp]['collectedValues'][] = $this->shortenValue($value);
                $this->ontologyMapping[$fullProp]['collectedValues'] = array_filter(array_unique($this->ontologyMapping[$fullProp]['collectedValues']),'strlen');
            } else if ($valuesCount == $numberValuesSaved) {
                $this->ontologyMapping[$fullProp]['collectedValues'][] = '...';
            }
        }
    }

    private function shortenValue($value) 
    {
        if (!is_string($value)) return "";
        return Utf8Encoder::toUTF8(substr($value, 0, 100));
    }

    public function mapOntology($data, $import)
    {
        $mapping = $import->getOntologyMapping();
        foreach ($data as $key => $row) {
            $newRow = [];
            // Fix bad prop names
            foreach($row as $prop => $value) {
                $row[$this->fixPropName($prop)] = $value;
            }

            foreach ($mapping as $prop => $mappedObject) {
                $mappedProp = $mappedObject['mappedProperty'];
                $explodedProp = explode('/', $prop);
                // Map nested properties first (if you didn't map the parent property then it will be gone
                // and you'll no longer able to access the subproperty)
                if (2 == count($explodedProp)) {
                    $prop = $explodedProp[0];
                    $subProp = $explodedProp[1];
                    if (!in_array($mappedProp, ['/', '']) && isset($row[$prop]) && isset($row[$prop][$subProp])) {
                        $newRow = $this->mapProperty($newRow, $mappedProp, $subProp, $row[$prop][$subProp]);
                    }
                }
                // Map standard properties
                if (1 == count($explodedProp)) {
                    if (!in_array($mappedProp, ['/', '']) && isset($row[$prop])) {
                        $newRow = $this->mapProperty($newRow, $mappedProp, $prop, $row[$prop]);
                    }
                }
            }

            foreach ($mapping as $searchProp => $mappedObject) {
                $mappedProp = $mappedObject['mappedProperty'];
            }

            if (isset($newRow['fullAddress'])) {
                $newRow['customFormatedAddress'] = $newRow['fullAddress'];
            }
            // convert lat/long numeric into string
            if (isset($newRow['latitude']) && is_numeric($newRow['latitude'])) {
                $newRow['latitude'] = ''.$newRow['latitude'];
            }
            if (isset($newRow['longitude']) && is_numeric($newRow['longitude'])) {
                $newRow['longitude'] = ''.$newRow['longitude'];
            }

            $data[$key] = $newRow;
        }

        return $data;
    }

    // oldRow have $prop => $value. newRow will have $mappedProp => $value
    private function mapProperty($newRow, $mappedProp, $prop, $value)
    {
        // We allow mapping multiple fields to "categories", and we remember
        // here which fields gave which categories
        if ('categories' == $mappedProp) {
            if (is_string($value)) $value = preg_split('/[,;]+/', $value); // convert to Array
            $value = array_merge($newRow[$mappedProp] ?? [], [$prop => $value]);
        }

        // replacing existing value only if not set, or if categories because values have been merged
        if (!isset($newRow[$mappedProp]) || 'categories' == $mappedProp) {
            $newRow[$mappedProp] = $value;
        }

        return $newRow;
    }
}
