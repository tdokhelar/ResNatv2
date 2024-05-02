<?php

namespace App\Controller\Admin;

use Doctrine\ODM\MongoDB\DocumentManager;
use Sonata\AdminBundle\Controller\CRUDController as Controller;
use Symfony\Component\HttpFoundation\Response;
use App\Document\Option;
use App\Document\Category;
class CategoryAdminController extends Controller
{
    public function __construct(DocumentManager $dm)
    {
        $this->dm = $dm;
    }

    public function listAction()
    {
        return $this->treeAction();
    }

    public function treeAction()
    {
        $this->admin->checkAccess('list');
        $config = $this->dm->get('Configuration')->findConfiguration();

        return $this->renderWithExtraParams('admin/list/taxonomy-admin.twig', [
            'config' => $config,
            'action' => 'list'
        ], null);
    }

    public function editTaxonomyAction()
    {
        $newObjectIds = [];
        $items = $this->getRequest()->get('items') ?? [];
        foreach($items as $item) {
            $isNew = array_key_exists('new', $item);
            $item['js_id'] = $item['id'];
            unset($item['id']); // remove fake id created by javascript
            if ($item['type'] == 'Group') { $item['type'] = 'Category'; } // fix type, Group is clearer than Category in the API result

            if ($isNew) {
                $object = $item['type'] == 'Option' ? new Option() : new Category();
            } else {
                $object = $this->dm->get(ucfirst($item['type']))->find($item['js_id']);
            }

            foreach($item as $prop => $value) {
                if (in_array($prop, ['parent', 'options', 'subcategories'])) continue;
                $setter = 'set'.ucfirst($prop);
                if ($value === "true") $value = true;
                if ($value === "false") $value = false;
                if ($value === "null") $value = null;
                if (method_exists($object, $setter)) {
                    $object->$setter($value);
                }
            }

            if (empty($item['parentId'])) $object->setParent(null);
            else {
                if (str_contains($item['parentId'], 'NEW')) {
                    $item['parentId'] = $newObjectIds[$item['parentId']];
                }
                if ($object->getParentId() != $item['parentId']) {
                    $parentClass = $item['type'] == 'Option' ? 'Category' : 'Option';
                    $parent = $this->dm->get($parentClass)->find($item['parentId']);
                    if ($parent) $object->setParent($parent, false);
                }
            }

            $this->dm->persist($object);
            $this->dm->flush();
            $this->dm->clear(); // remove doctrine cache to load the new categories just created (in case multiple flush in same request)
            if ($isNew) $newObjectIds[$item['js_id']] = $object->getId();
        }

        $this->dm->query('Option')
            ->field('id')->in($this->getRequest()->get('deletedOptionIds') ?? [])
            ->batchRemove();
        $this->dm->query('Category')->field('id')
            ->in($this->getRequest()->get('deletedCategoryIds') ?? [])
            ->batchRemove();

        // update one option so the TaxonomyJson cache is invalidated
        // (the deletions does not trigger cache invalidation, cause it's based on last updatedAt)
        $this->dm->query('Option')->updateOne()
            ->field('name')->exists(true)
            ->field('updatedAt')->set(new \DateTime())->execute();

        $response = new Response(json_encode(['newIds' => $newObjectIds]));;
        $response->headers->set('Access-Control-Allow-Origin', '*');
        $response->headers->set('Content-Type', 'application/json');
        return $response;
    }
}
