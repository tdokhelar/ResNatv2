<?php

namespace App\Admin;

use Sonata\AdminBundle\Form\FormMapper;
use Sonata\AdminBundle\Form\Type\ModelType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;

class ConfigurationAdmin extends ConfigurationAbstractAdmin
{
    protected function configureFormFields(FormMapper $formMapper)
    {
        $imagesOptions = [
            'class' => 'App\Document\ConfImage',
            'placeholder' => $this->trans('images.placeholder'),
            'mapped' => true,
        ];

        $container = $this->getConfigurationPool()->getContainer();

        $formMapper
            ->halfPanel('main');
        if ($container->getParameter('use_as_saas')) {
            $formMapper
                ->add('publishOnSaasPage', null, ['label_trans_params' => ['url' => $container->getParameter('base_url')]]);
        }
        $formMapper
                ->add('metaRobotsIndexFollow')
                ->add('appName')
                ->add('appBaseline')
                ->add('appTags')
                ->add('locale', ChoiceType::class, ['choices' => [
                    'Basque' => 'eu',
                    'Breton' => 'br',
                    'Deutsch' => 'de',
                    'English' => 'en',
                    'Español' => 'es',
                    'Français' => 'fr', 
                    'Português' => 'pt',
                    'Türkçe' => 'tr'
                ]])
                ->add('customDomain', UrlType::class, ['help_trans_params' => ['ip' => $_SERVER['SERVER_ADDR']]])
                ->add('dataLicenseUrl')
            ->end()
            ->halfPanel('images')
                ->add('logo', ModelType::class, $imagesOptions)
                ->add('logoInline', ModelType::class, $imagesOptions)
                ->add('socialShareImage', ModelType::class, $imagesOptions)
                ->add('favicon', ModelType::class, $imagesOptions)
            ->end()
            ->halfPanel('pages')
                ->add('activateHomePage', CheckboxType::class, ['label' => 'config_home.fields.activateHomePage'])
                ->add('activatePartnersPage')
                ->add('partnerPageTitle')
                ->add('activateAbouts')
                ->add('aboutHeaderTitle')
            ->end()
            ->halfPanel('text')
                ->add('elementDisplayName')
                ->add('elementDisplayNameDefinite')
                ->add('elementDisplayNameIndefinite')
                ->add('elementDisplayNamePlural')
            ->end()
            ->halfPanel('import-export')
                ->add('import-export-actions', 
                    TextType::class,
                    ['label' => false, 'mapped' => false, 'attr' => ['class' => 'gogo-config-import-export']]
                )
            ->end()
        ;
    }
}
