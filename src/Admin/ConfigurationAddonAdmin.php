<?php

namespace App\Admin;
use Sonata\AdminBundle\Form\FormMapper;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use App\Helper\GoGoHelper;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;

class ConfigurationAddonAdmin extends ConfigurationAbstractAdmin
{
    protected $baseRouteName = 'gogo_core_bundle_config_addon_admin_classname';
    protected $baseRoutePattern = 'gogo/core/configuration-addon';
    private $dm;
    private $config;
    private $addons;

    protected function configureFormFields(FormMapper $formMapper)
    {
        $this->dm = GoGoHelper::getDmFromAdmin($this);
        $this->config = $this->dm->get('Configuration')->findConfiguration();
        $this->addons = [
            'siret'
        ];

        $formMapper
            ->panel('addons')
            ->add('activeAddons', TextType::class, [
                'mapped' => false,
                'label' => false,
                'attr' => [
                    'class' => 'gogo-addons',
                    'addons' => $this->getAddonsData(),
                ]
            ])
            ->add('appName', HiddenType::class) /* Trick needed to trigger the form update event */
            ->end();
    }

    public function preUpdate($object)
    {
        $formData = $this->getRequest()->request->all();
        $activeAddons = $formData['activeAddons'] ?? [];

        $this->config->setActiveAddons($activeAddons);
        $this->dm->persist($this->config);
    }
    
    private function getAddonsData()
    {
        $activeAddons = $this->config->getActiveAddons();
        $addonsData = [];

        foreach ($this->addons as $addOn) {
            $checkSettings = $this->checkSettings($addOn);
            $addonsData[] = [
                'name' => $addOn,
                'is_active' => in_array($addOn, $activeAddons),
                'is_ready' => $checkSettings['is_ready'],
                'message_before_activation' => $checkSettings['message_before_activation'],
                'message' => $checkSettings['message']
            ];
        }

        return $addonsData;
    }
    
    private function checkSettings($addon)
    {
        $response = [
            'is_ready' => true,
            'message_before_activation' => '',
            'message' => ''
        ];

        switch ($addon) {
            case 'siret':
            // Check if siret custom field exists in element form
            $formFields = $this->config->getElementFormFields();
            if (array_search('siret', array_column($formFields, 'name'))) {
                $response['message_before_activation'] = $this->trans('addons.siret.message.siretFieldExists');
            }
        }

        return $response;
    }
}
