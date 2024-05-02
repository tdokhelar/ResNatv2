<?php

namespace App\Admin;

use Sonata\AdminBundle\Form\FormMapper;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;

class DirectModerationConfigurationAdmin extends GoGoAbstractAdmin
{
    protected function configureFormFields(FormMapper $formMapper)
    {
        $formMapper
            ->add('active', CheckboxType::class)
            ->add('activeInIframe', CheckboxType::class)
            ->add('allow_role_anonymous', CheckboxType::class)
            ->add('allow_role_user', CheckboxType::class)
            ->add('allow_role_admin', CheckboxType::class)
            ->add('allow_owner', CheckboxType::class);
    }
}