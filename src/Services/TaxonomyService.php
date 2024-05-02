<?php

namespace App\Services;

use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use JMS\Serializer\SerializationContext;
use JMS\Serializer\SerializerInterface;
use Doctrine\ODM\MongoDB\DocumentManager;

class TaxonomyService
{
    protected $serializer;
    protected $dm;

    public function __construct(SerializerInterface $serializer, DocumentManager $dm)
    {
        $this->serializer = $serializer;
        $this->dm = $dm;
    }

    public function getTaxonomyJson()
    {
        return $this->getCache($this->cacheKey('taxonomy'), function () {
            return $this->generateTaxonomyJson();
        });
    }

    public function getOptionsJson()
    {
        return $this->getCache($this->cacheKey('options'), function () {
            return $this->generateOptionsJson();
        });
    }

    private function getCache($cacheKey, $callback)
    {
        $cache = new FilesystemAdapter();
        // Manually re implement the cache->get method because we had a lot of issue using it..
        // The moethod was sometime loading for ever and ends up with 504
        // We couldn't figure out what was going wrong, the cache key was correct but it's like
        // it couldn't fetch the proper file on the system. by changing the cacheKey everything
        // was working again as expected...
        if (!$cache->hasItem($cacheKey)) {
            $cache->deleteItem($cacheKey); // maybe this deleteItem is making the diff and solving our problem ?
            $cacheItem = $cache->getItem($cacheKey);
            $cacheItem->set($callback());
            $isSaved = $cache->save($cacheItem);
            return $cacheItem->get();
        }
        return $cache->getItem($cacheKey)->get();
    }

    private function cacheKey($name) {
        $lastOption = $this->dm->query('Option')->sort('updatedAt', 'desc')->getOne();
        $lastOptionKey = $lastOption ? $lastOption->getUpdatedAt()->format('U') : 'none';
        $lastCategory = $this->dm->query('Category')->sort('updatedAt', 'desc')->getOne();
        $lastCategoryKey = $lastCategory ? $lastCategory->getUpdatedAt()->format('U') : 'none';
        $dbName = $this->dm->getConfiguration()->getDefaultDB();
        return "data_{$name}_cache_{$dbName}_{$lastOptionKey}_{$lastCategoryKey}";
    }

    // Create flatten option list
    private function generateOptionsJson()
    {
        $options = $this->dm->get('Option')->findAll();

        $optionsSerialized = [];
        foreach ($options as $option) {
            try {
                $optionsSerialized[] = $this->serializer->serialize($option, 'json', SerializationContext::create()->setGroups(['semantic']));
            } catch (\Exception $e) {}
        }
        return '['.implode(', ', $optionsSerialized).']';
    }

    // Create hierachic taxonomy
    private function generateTaxonomyJson()
    {
        $rootCategories = $this->dm->get('Category')->findRootCategories();

        if (0 == count($rootCategories)) {
            return '[]';
        } else {
            $rootCategoriesSerialized = [];
            foreach ($rootCategories as $key => $rootCategory) {
                $rootCategoriesSerialized[] = $this->serializer->serialize($rootCategory, 'json');
            }
            return '['.implode(', ', $rootCategoriesSerialized).']';
        }
    }
}
