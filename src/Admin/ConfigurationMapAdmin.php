<?php
/**
 * @Author: Sebastian Castro
 * @Date:   2017-03-28 15:29:03
 * @Last Modified by:   Sebastian Castro
 * @Last Modified time: 2018-04-22 19:45:15
 */

namespace App\Admin;

use Sonata\AdminBundle\Form\FormMapper;
use Sonata\AdminBundle\Form\Type\AdminType;
use Sonata\AdminBundle\Form\Type\ChoiceFieldMaskType;
use Sonata\AdminBundle\Form\Type\CollectionType;
use Sonata\AdminBundle\Form\Type\ModelType;
use Sonata\FormatterBundle\Form\Type\SimpleFormatterType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use App\Form\GeojsonLayerType;
use App\Helper\GoGoHelper;

class ConfigurationMapAdmin extends ConfigurationAbstractAdmin
{
    protected $baseRouteName = 'gogo_core_bundle_config_map_admin_classname';

    protected $baseRoutePattern = 'gogo/core/configuration-map';

    protected function configureFormFields(FormMapper $formMapper)
    {
        $featureStyle = ['class' => 'col-md-6 col-lg-3 gogo-feature'];
        $featureFormOption = ['delete' => false, 'required' => false, 'label_attr' => ['style' => 'display:none']];
        $featureFormTypeOption = ['edit' => 'inline'];
        $dm = GoGoHelper::getDmFromAdmin($this);
        $config = $dm->get('Configuration')->findConfiguration();

        $featureFormOption = ['delete' => false, 'required' => false, 'label_attr' => ['style' => 'display:none']];
        $featureFormTypeOption = ['edit' => 'inline'];
        
        $router = $this->getConfigurationPool()->getContainer()->get('router');
        $subscriptionUrl = $router->generate('gogo_mail_draft_automated', ['mailType' => 'subscription']);
        $subscriptionProperties = $dm->get('Element')->findAllCustomProperties() + [
            'address' => 'address',
            'optionsString' => 'optionsString',
            'geo' => 'geo',
            'openHours' => 'openHours'
        ];
        
        $subscriptionPropertiesChanged = [];
        foreach ($subscriptionProperties as $key => $value) {
            $trans = str_replace('config_map.subscription.fields.', '', $this->trans('config_map.subscription.fields.' . $value));
            $subscriptionPropertiesChanged[$trans] = $value;
        }
        ksort($subscriptionPropertiesChanged);
        
        $formMapper
            ->tab('params')
                ->panel('map')
                    ->add('defaultTileLayer', ModelType::class, ['class' => 'App\Document\TileLayer'])
                    ->add('geojsonLayers', CollectionType::class, [
                        'allow_add' => true,
                        'allow_delete' => true,
                        'delete_empty' => true,
                        'entry_type' => GeojsonLayerType::class,
                        'attr' => ['class' => 'geojsonlayers'],
                        
                    ])
                    ->add('defaultViewPicker', TextType::class, ['mapped' => false, 'attr' => [
                                                        'class' => 'gogo-viewport-picker',
                                                        'data-tile-layer' => $config->getDefaultTileLayer()->getUrl(),
                                                        'data-default-bounds' => json_encode($config->getDefaultBounds()),
                                                        'picker_id' => 'default',
                                                        'label' => $this->trans('config_map.geocoding.countryCodes')
                                                    ]])
                    ->add('defaultNorthEastBoundsLat', HiddenType::class, ['attr' => ['class' => 'bounds NELat']])
                    ->add('defaultNorthEastBoundsLng', HiddenType::class, ['attr' => ['class' => 'bounds NELng']])
                    ->add('defaultSouthWestBoundsLat', HiddenType::class, ['attr' => ['class' => 'bounds SWLat']])
                    ->add('defaultSouthWestBoundsLng', HiddenType::class, ['attr' => ['class' => 'bounds SWLng']])
                ->end()
                ->panel('cookies')
                    ->add('saveViewportInCookies', CheckboxType::class)
                    ->add('saveTileLayerInCookies', CheckboxType::class)
                ->end()
            ->end()
            ->tab('geocoding')
                ->panel('bounds')
                    ->add('geocodingBoundsType', ChoiceFieldMaskType::class, [
                        'choices' => [
                            $this->trans('config_map.geocoding.none') => 'none',
                            $this->trans('config_map.geocoding.defaultView') => 'defaultView',
                            $this->trans('config_map.geocoding.countryCodes') => 'countryCodes',
                            $this->trans('config_map.geocoding.viewPicker') => 'viewPicker',
                        ],
                        'attr' => ['class' => 'geocoding-bounds-type'],
                        'data' => $config->getGeocodingBoundsType() ?: 'none' ,
                        'map' => [
                            'countryCodes' => ['geocodingBoundsByCountryCodes'],
                            'viewPicker' => ['geocodingBoundsByViewPicker'],
                        ]
                    ])
                    ->add('geocodingBoundsByCountryCodes', TextType::class)
                    ->add('geocodingBoundsByViewPicker', TextType::class, ['mapped' => false, 'attr' => [
                        'class' => 'gogo-viewport-picker',
                        'data-tile-layer' => $config->getDefaultTileLayer()->getUrl(),
                        'data-default-bounds' => $config->getGeocodingBounds() ? json_encode($config->getGeocodingBounds()) : json_encode($config->getDefaultBounds()) ,
                        'picker_id' => 'geocoding',
                        'label' => $this->trans('config_map.geocoding.countryCodes')
                    ]])
                    ->add('geocodingNorthEastBoundsLat', HiddenType::class, ['attr' => ['class' => 'bounds NELat']])
                    ->add('geocodingNorthEastBoundsLng', HiddenType::class, ['attr' => ['class' => 'bounds NELng']])
                    ->add('geocodingSouthWestBoundsLat', HiddenType::class, ['attr' => ['class' => 'bounds SWLat']])
                    ->add('geocodingSouthWestBoundsLng', HiddenType::class, ['attr' => ['class' => 'bounds SWLng']])
                ->end()
            ->end()
            ->tab('features')
                ->panel('favoriteFeature', $featureStyle)
                    ->add('favoriteFeature', AdminType::class, $featureFormOption, $featureFormTypeOption)->end()
                ->panel('shareFeature', $featureStyle)
                    ->add('shareFeature', AdminType::class, $featureFormOption, $featureFormTypeOption)->end()
                ->panel('reportFeature', $featureStyle)
                    ->add('reportFeature', AdminType::class, $featureFormOption, $featureFormTypeOption)->end()
                ->panel('stampFeature', $featureStyle)
                    ->add('stampFeature', AdminType::class, $featureFormOption, $featureFormTypeOption)->end()
                ->panel('listModeFeature', $featureStyle)
                    ->add('listModeFeature', AdminType::class, $featureFormOption, $featureFormTypeOption)->end()
                ->panel('layersFeature', $featureStyle)
                    ->add('layersFeature', AdminType::class, $featureFormOption, $featureFormTypeOption)->end()
                ->panel('mapDefaultViewFeature', $featureStyle)
                    ->add('mapDefaultViewFeature', AdminType::class, $featureFormOption, $featureFormTypeOption)->end()
                ->panel('exportIframeFeature', $featureStyle)
                    ->add('exportIframeFeature', AdminType::class, $featureFormOption, $featureFormTypeOption)->end()
                ->panel('pendingFeature', $featureStyle)
                    ->add('pendingFeature', AdminType::class, $featureFormOption, $featureFormTypeOption)->end()
                ->panel('subscribeFeature', ['class' => 'col-md-3 col-sm-12 clear-row'])
                    ->add('subscribeFeature', AdminType::class, $featureFormOption, $featureFormTypeOption)->end()
                    
    
                ->panel('subscriptionMail', [
                    'class' => 'col-md-9 col-sm-12 subscription-mail',
                    'label_trans_params' => [
                        '%url%' => $subscriptionUrl,
                        '%element%' => $config->getElementDisplayNameDefinite()]
                ])
                    ->add('subscriptionMail',
                        AdminType::class,
                        $featureFormOption,
                        $featureFormTypeOption
                    )
                    ->add('subscription.subscriptionProperties',
                        ChoiceType::class,
                        [
                            'choices' => $subscriptionPropertiesChanged,
                            'multiple' => true, 'required' => false
                        ]
                    )
                ->end()
            ->end()
            ->tab('messages')
                ->panel('message_config', ['class' => 'gogo-feature'])
                    ->add('customPopupFeature', AdminType::class, $featureFormOption, $featureFormTypeOption)
                    ->add('customPopupText', SimpleFormatterType::class, [
                            'format' => 'richhtml',
                            'label_attr' => ['style' => 'margin-top: 20px'],
                            'ckeditor_context' => 'full',
                    ])
                    ->add('customPopupId')
                    ->add('customPopupShowOnlyOnce')
                ->end()
            ->end()
        ;
    }
}
