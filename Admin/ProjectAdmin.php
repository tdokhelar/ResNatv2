<?php

namespace App\Admin;

use Sonata\AdminBundle\Datagrid\DatagridMapper;
use Sonata\AdminBundle\Datagrid\ListMapper;
use Sonata\AdminBundle\Route\RouteCollection;

class ProjectAdmin extends GoGoAbstractAdmin
{
    protected $datagridValues = [
        '_page' => 1,
        '_sort_order' => 'DESC',
        '_sort_by' => 'createdAt',
    ];
    
    protected function configureRoutes(RouteCollection $collection)
    {
        $collection->get("create")->setPath('../project/new');
    }

    protected function configureDatagridFilters(DatagridMapper $datagridMapper)
    {
        $datagridMapper->add('name')
                       ->add('domainName')
                       ->add('adminEmails')
                       ->add('pinned');
    }

    protected function configureListFields(ListMapper $listMapper)
    {
        $listMapper
            ->add('name', null, ['template' => 'admin/partials/list_project_name.html.twig'])
            ->add('description')
            ->add('dataSize', null)
            ->add('adminEmails')
            ->add('published', null)
            ->add('pinned', null, ['editable' => true])
            ->add('createdAt')
            ->add('lastActivity')
            ->add('_action', 'actions', [
                'actions' => [
                    // 'show' => array(),
                    // 'edit' => array(),
                    'delete' => [],
                ],
            ]);
    }
}
