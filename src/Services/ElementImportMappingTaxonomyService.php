<?php

namespace App\Services;

use Doctrine\ODM\MongoDB\DocumentManager;
use Symfony\Contracts\Translation\TranslatorInterface;

class ElementImportMappingTaxonomyService
{
    protected $ontologyMapping;

    public function __construct(DocumentManager $dm, TranslatorInterface $t)
    {
        $this->dm = $dm;
        $this->t = $t;
        $this->mappingTableIds = [];
    }

    public function collectTaxonomy($data, $import)
    {
        $taxonomyMapping = $import->getTaxonomyMapping();
        // delete obsolte mapping (if an option have been deleted, but is still in the mapping)
        $allOptionsIds = $this->dm->query('Option')->getIds();
        foreach ($taxonomyMapping as $key => $mappedObject) {
            $taxonomyMapping[$key]['mappedCategoryIds'] = array_filter($mappedObject['mappedCategoryIds'], function ($el) use ($allOptionsIds) {
                return in_array($el, $allOptionsIds);
            });
        }
        $allNewCategories = [];
        $this->createOptionsMappingTable();

        foreach ($data as $row) {
            if (isset($row['categories'])) {
                foreach($row['categories'] as $originField => $categories) {
                    $categories = is_array($categories) ? $categories : preg_split("[,;]+/", $categories);
                    foreach ($categories as $category) {
                        $categoryId = 0;
                        if (is_array($category)) {
                            $categoryId = $category['id'];
                            $category = $category['name'];
                        }
                        $category = trim($category);
                        $category = str_replace('.', '_', $category);
                        $categoryWithProp = "{$originField}@$category@$categoryId";
                        if (!in_array($categoryWithProp, $allNewCategories)) {
                            $allNewCategories[] = $categoryWithProp;
                        }
                        if ($category) {
                            if (!array_key_exists($categoryWithProp, $taxonomyMapping)) {
                                $categorySlug = slugify($category);
                                $mappedCategoryId = array_key_exists($categorySlug, $this->mappingTableIds) ? $this->mappingTableIds[$categorySlug]['id'] : '';

                                $taxonomyMapping[$categoryWithProp] = [
                                    'mappedCategoryIds' => [$mappedCategoryId],
                                    'collectedCount' => 1,
                                    'fieldName' => $originField,
                                    'inputId' =>  $categoryId,
                                    'inputValue' => $category
                                ];
                                if (!$mappedCategoryId) {
                                    $import->setNewTaxonomyToMap(true);
                                }
                            } else {
                                $taxonomyMapping[$categoryWithProp]['collectedCount']++;
                                $taxonomyMapping[$categoryWithProp]['fieldName'] = $originField;
                            }
                        }
                    }
                }
            }
        }
        // delete no more used categories
        foreach ($taxonomyMapping as $categoryWithProp => $mappedCategory) {
            if (!in_array($categoryWithProp, $allNewCategories)) {
                unset($taxonomyMapping[$categoryWithProp]);
            }
        }
        foreach($taxonomyMapping as &$mappedObject) {
            $mappedObject['collectedPercent'] = $mappedObject['collectedCount'] / count($data) * 100;
        }
        $import->setTaxonomyMapping($taxonomyMapping);
    }

    public function mapTaxonomy($data, $import)
    {
        $mapping = $import->getTaxonomyMapping();

        foreach ($data as $key => $row) {
            if (isset($row['categories'])) {
                $elementCategories = [];
                $categoriesIds = [];
                $newParentCategoryIds = [];
                foreach ($row['categories'] as $originField => $categories) {
                    foreach($categories as $category) {
                        $catName = is_array($category) ? $category['name'] : $category;
                        $catId = is_array($category) ? $category['id'] : 0;
                        $catName = str_replace('.', '_', trim($catName));
                        $categoryWithProp = "{$originField}@$catName@$catId";
                        if (isset($mapping[$categoryWithProp]['mappedCategoryIds']) && $mapping[$categoryWithProp]['mappedCategoryIds']) {
                            foreach ($mapping[$categoryWithProp]['mappedCategoryIds'] as $mappedCategoryId) {
                                if (array_key_exists($mappedCategoryId, $this->mappingTableIds)) {
                                    $newcat['id'] = $this->mappingTableIds[$mappedCategoryId]['id'];
                                    if (!in_array($newcat['id'], $categoriesIds)) {
                                        if (isset($category['index'])) {
                                            $newcat['index'] = $category['index'];
                                        }
                                        if (isset($category['description'])) {
                                            $newcat['description'] = $category['description'];
                                        }
                                        $elementCategories[] = $newcat;
                                        $categoriesIds[] = $newcat['id'];
                                        $parentIds = $this->mappingTableIds[$mappedCategoryId]['idAndParentsId'];
                                        // Adds also the parent categories
                                        foreach ($parentIds as $id) {
                                            if (!in_array($id, $newParentCategoryIds)) {
                                                $newParentCategoryIds[] = $id;
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
                foreach ($newParentCategoryIds as $newParentCategoryId) {
                    if (!in_array($newParentCategoryId, $categoriesIds)) {
                        $newParentCategory['id'] = $newParentCategoryId;
                        $newParentCategory['index'] = 0;
                        $newParentCategory['description'] = '';
                        $elementCategories[] =  $newParentCategory;
                    }
                }
                $data[$key]['categories'] = $elementCategories;
            }
        }

        return $data;
    }

    private function createOptionsMappingTable($options = null)
    {
        if (null === $options) {
            $options = $this->dm->get('Option')->findAll();
        }

        foreach ($options as $option) {
            $ids = [
                'id' => $option->getId(),
                'name' => $option->getName(),
                'idAndParentsId' => $option->getIdAndParentOptionIds(),
            ];
            $this->mappingTableIds[slugify($option->__toString())] = $ids;
            $this->mappingTableIds[slugify($option->getName())] = $ids;
            $this->mappingTableIds[strval($option->getId())] = $ids;
            if ($option->getCustomId()) {
                $this->mappingTableIds[slugify($option->getCustomId())] = $ids;
            }
        }
    }
}