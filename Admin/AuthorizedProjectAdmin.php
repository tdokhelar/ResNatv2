<?php

namespace App\Admin;

use Sonata\AdminBundle\Datagrid\DatagridMapper;
use Sonata\AdminBundle\Datagrid\ListMapper;
use Sonata\AdminBundle\Form\FormMapper;

class AuthorizedProjectAdmin extends GoGoAbstractAdmin
{

    public function getTemplate($name)
    {
        switch ($name) {
          case 'list': return 'admin/list/list_authorized-projects.html.twig';
            break;
          default: return parent::getTemplate($name);
            break;
        }
    }

    protected function configureFormFields(FormMapper $formMapper)
    {
        $formMapper->add('url');
        if ($this->getSubject()->getID()) {
            $formMapper->add('apiKey', null, [
                'attr' => ['class' => 'gogo-api-key'],
            ]);
        }
        $formMapper->add('isActivated');
    }

    protected function configureDatagridFilters(DatagridMapper $datagridMapper)
    {
        $datagridMapper->add('url');
        $datagridMapper->add('isActivated');
    }

    protected function configureListFields(ListMapper $listMapper)
    {
        $listMapper
            ->addIdentifier('url')
            ->addIdentifier('apiKey')
            ->add('isActivated', null, ['editable' => true])
            ->add('_action', 'actions', [
                'actions' => [
                    'edit' => [],
                    'delete' => [],
                    'changesHistory' => ['template' => 'admin/partials/list_changes_history.html.twig'],
                ],
            ]);
    }
}
