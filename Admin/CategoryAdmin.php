<?php
/**
 * @Author: Sebastian Castro
 * @Date:   2017-03-28 15:29:03
 * @Last Modified by:   Sebastian Castro
 * @Last Modified time: 2018-07-08 16:42:20
 */

namespace App\Admin;

use Sonata\AdminBundle\Datagrid\ListMapper;
use Sonata\AdminBundle\Form\FormMapper;
use Sonata\AdminBundle\Form\Type\CollectionType;
use Sonata\AdminBundle\Form\Type\ModelType;
use Sonata\AdminBundle\Route\RouteCollection;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use App\Form\OptionLiteType;
use App\Helper\GoGoHelper;

class CategoryAdmin extends GoGoAbstractAdmin
{
    protected $baseRouteName = 'admin_app_category';
    protected $baseRoutePattern = 'admin_app_category';

    protected function configureListFields(ListMapper $listMapper)
    {
        $listMapper
          ->add('name')
          ->add('_action', 'actions', [
               'actions' => [
                    'tree' => ['template' => 'admin/partials/list__action_tree.html.twig'],
               ],
            ]);
    }

    protected function configureRoutes(RouteCollection $collection)
    {
        $collection->add('tree', $this->getRouterIdParameter().'/tree');
    }
}
