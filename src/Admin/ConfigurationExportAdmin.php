<?php

namespace App\Admin;

use Sonata\AdminBundle\Datagrid\ListMapper;
use App\Document\Configuration\ConfigurationExport;
use App\Helper\GoGoHelper;
use App\Services\ElementExportService;
use Sonata\AdminBundle\Form\FormMapper;
use Sonata\AdminBundle\Route\RouteCollection;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;

class ConfigurationExportAdmin extends GoGoAbstractAdmin
{
    protected $baseRouteName = 'gogo_core_bundle_config_exports_admin_classname';

    protected $baseRoutePattern = 'gogo/core/configuration-exports';
    

    public function getTemplate($name)
    {
        switch ($name) {
          case 'list': return 'admin/list/list_configuration-export.html.twig';
            break;
          case 'edit': return 'admin/edit/edit_export.html.twig';
            break;
          default: return parent::getTemplate($name);
            break;
        }
    }
    
    public function getExportFields()
    {
        $dm = GoGoHelper::getDmFromAdmin($this);
        
        $elementExportService = new ElementExportService($dm);
        return $elementExportService->getExportFields();
    }

    protected function configureFormFields(FormMapper $formMapper)
    {
        $formMapper
          ->panel('export')
            ->add('name', TextType::class)
            ->add('exportProperties',
              ChoiceType::class,
              [
                  'choices' => $this->getExportFields(),
                  'multiple' => true,
                  'required' => false,
                  'sortable' => true,
                  'choice_label' => function ($choice, $key, $value) {
                    $label = $this->trans('elements.fields.' . $value);
                    if (startsWith($label, 'elements.fields.')) {
                      $label = $key;
                    }
                    if (startsWith($value, 'gogo-option')) {
                      $label = $this->trans('elements.category') . ': ' . $label;
                    }
                    return $label;
                  },
                  'choice_attr' => function($choice, $key, $value) {
                    $attr = [];
                    if (startsWith($choice, 'gogo-option')) {
                      $attr = ['data-type' => 'category'];
                    }
                    return $attr;
                  }
              ]
            )
            ->add('config-export-actions',
                TextType::class,
                ['label' => false, 'mapped' => false, 'attr' => ['class' => 'gogo-config-export-format']]
            );
    }
    
    public function preUpdate($alias)
    {
        // keep fields order on update
        $uniqid = $this->getRequest()->query->get('uniqid');
        $formData = $this->getRequest()->request->get($uniqid);
        if (array_key_exists('export__exportProperties', $formData)) {
          $export__exportProperties = $formData['export__exportProperties'];
          $configurationExport = new ConfigurationExport();
          $configurationExport->setExportProperties($export__exportProperties);
          $alias->setExport($configurationExport);
        }
    }
    
    protected function configureListFields(ListMapper $listMapper)
    {
        $listMapper
            ->addIdentifier('name')
            ->add('_action', 'actions', [
                'actions' => [
                    'edit' => [],
                    'delete' => []
                ],
            ])
        ;
    }
    
    public function configureRoutes(RouteCollection $collection) {
      $collection->remove('export');
    }
}
