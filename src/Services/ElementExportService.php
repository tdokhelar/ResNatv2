<?php

namespace App\Services;

use Doctrine\ODM\MongoDB\DocumentManager;

class ElementExportService
{
    public function __construct(DocumentManager $dm)
    {
        $this->dm = $dm;
    }

    public function getExportFields()
    {
        $config = $this->dm->get('Configuration')->findConfiguration();
        // BASIC FIELDS
        $basicFields = [
          'id' => 'id',
          'name' => 'name',
          'categories' => 'optionsString',
          'categories_ids' => 'optionIds',
          'latitude' => 'geo.latitude',
          'longitude' => 'geo.longitude',
          'streetNumber' => 'address.streetNumber',
          'streetAddress' => 'address.streetAddress',
          'addressLocality' => 'address.addressLocality',
          'postalCode' => 'address.postalCode',
          'addressCountry' => 'address.addressCountry',
          'status' => 'status',
          'moderationState' => 'moderationState',
          'source' => 'sourceKey',
          'images' => 'gogo-custom-images',
          'files' => 'gogo-custom-files',
          'createdAt' => 'createdAt',
          'updatedAt' => 'updatedAt',
          'openHours' => 'openHours',
          'oldId' => 'oldId',
          'userOwnerEmail' => 'userOwnerEmail'
        ];
        // CUSTOM FIELDS        
        $formFieldsMapping = $config->getElementFormFieldsMapping();
        $props = $this->dm->get('Element')->findDataCustomProperties();
        $customFields = [];
        foreach ($props as $key => $prop) {
          if (!isset($basicFields[$prop])) {
            $type = isset($formFieldsMapping[$prop]) ? '-'.$formFieldsMapping[$prop]->type : '';
            $customFields[$prop] = 'gogo-custom' . $type . ':' . $prop;
          }
        }
        // CATEGORIES FIELDS
        $rootCategories = $this->dm->get('Category')->findRootCategories();
        $categoriesFields = [];
        foreach($rootCategories as $rootCatgeory) $this->recursivelyAddOptions($rootCatgeory, $categoriesFields);
        
        $exportFields = array_merge($basicFields, $customFields, $categoriesFields);
        
        // FORM FIELDS (for sorting)
        $formFields["id"] = "id";
        forEach($config->getElementFormFields() as $field) {
          if (array_key_exists('name', $field)) {
            if ( array_key_exists($field->name, $exportFields)) {
              $formFields[$field->name] = $field->name;
            }
          }
        }

        return array_merge($formFields, $basicFields, $customFields, $categoriesFields);
    }

    public function recursivelyAddOptions($category, &$categoriesFields) {
      foreach($category->getOptions() as $option) {
        $categoriesFields[$option->__toString()] = "gogo-option:@{$option->getId()}";
        foreach($option->getSubcategories() as $subcategory)
          $this->recursivelyAddOptions($subcategory, $categoriesFields);
      }
    }
}