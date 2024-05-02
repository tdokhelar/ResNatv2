<?php

namespace App\Admin;

use Sonata\AdminBundle\Form\FormMapper;
use Sonata\AdminBundle\Form\Type\ModelType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;

class ConfigurationHomeAdmin extends ConfigurationAbstractAdmin
{
    protected $baseRouteName = 'gogo_core_bundle_config_home_admin_classname';

    protected $baseRoutePattern = 'gogo/core/configuration-home';

    protected function configureFormFields(FormMapper $formMapper)
    {
        $imagesOptions = [
            'class' => 'App\Document\ConfImage',
            'mapped' => true,
        ];

        $formMapper
            ->add('activateHomePage')
            ->add('backgroundImage', ModelType::class, $imagesOptions)
            ->add('home.displayCategoriesToPick', CheckboxType::class)
            ->add('home.addElementHintText')
            ->add('home.seeMoreButtonText')
        ;
    }
}
