<?php

namespace App\Admin;

use Sonata\AdminBundle\Datagrid\DatagridMapper;
use Sonata\AdminBundle\Datagrid\ListMapper;
use Sonata\AdminBundle\Form\FormMapper;
use Sonata\AdminBundle\Form\Type\ModelType;
use Sonata\AdminBundle\Route\RouteCollection;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use App\Helper\GoGoHelper;
use App\Services\InteroperabilityService;
use App\Services\TaxonomyService;

class ImportAdmin extends GoGoAbstractAdmin
{
    public $config;
    public $elementId;
    
    public function getTemplate($name)
    {
        $isDynamic = "App\Document\ImportDynamic" == $this->getClass();
        switch ($name) {
            case 'edit': return 'admin/edit/edit_import.html.twig';
            break;
            case 'list': return $isDynamic ? 'admin/list/list_import_dynamic.html.twig' : 'admin/list/list_import.html.twig';
            break;
            default: return parent::getTemplate($name);
            break;
        }
    }

    protected function configureFormFields(FormMapper $formMapper)
    {
        $dm = GoGoHelper::getDmFromAdmin($this);
        $repo = $dm->get('Element');
        $formProperties = json_encode($repo->findFormProperties());
        $elementProperties = json_encode($repo->findDataCustomProperties());
        $this->config = $dm->get('Configuration')->findConfiguration();

        $container = $this->getConfigurationPool()->getContainer();
        $taxonomyService = $container->get(TaxonomyService::class);
        $optionsList = $taxonomyService->getOptionsJson();

        $isDynamic = $this->getSubject()->isDynamicImport();
        $title = $isDynamic ? 'imports.dynamic' : 'imports.static';
        $isPersisted = $this->getSubject()->getId();
        $sourceType = $this->getSubject()->getSourceType();

        $usersQuery = $dm->query('User');
        $usersQuery->addOr($usersQuery->expr()->field('roles')->exists(true))
                   ->addOr($usersQuery->expr()->field('groups')->exists(true));
        $formMapper
            ->tab('general')
                ->panel($title, ['class' => 'col-md-12'])
                    ->add('sourceName', null, ['required' => true])
                    ->add('file', FileType::class);
                    
        $isReadyForSynchronization = false;
        if ($isDynamic) {
            
            $isReadyForSynchronization = $sourceType === 'osm' && $this->config->getOsm()->isConfigured();
            if ($this->getSubject()->getSourceType() === 'gogocarto' && $this->getSubject()->getUrl()) {
                $interoperabilityService = $container->get(InteroperabilityService::class);
                $isReadyForSynchronization = $interoperabilityService->getAuthorizedProjectPermission($this->getSubject()->getGoGoCartoBaseUrl());
            }
            $isSynchronized = $this->getSubject()->getIsSynchronized();
            
            $elements = $dm->query('Element')
                ->field('sourceKey')->equals($this->getSubject()->getSourceName())
                ->select('_id', 'oldId', 'name')
                ->getArray();
            $idsToIgnoreChoices = [];
            foreach ($elements as $key => $value) {
                if (is_array($value) && array_key_exists('oldId', $value)) {
                    $idsToIgnoreChoices[$value['oldId']] = $value['oldId'] . ' => (' . $key . ') ' . $value['name'];
                }
            }
            $idsToIgnore = $this->getSubject()->getIdsToIgnore();
            foreach ($idsToIgnore as $key => $value) {
                $idsToIgnoreChoices[$value] = $value;
            }
            $idsToIgnoreChoices = array_flip($idsToIgnoreChoices);
            uasort($idsToIgnoreChoices, 'strnatcasecmp');
            
            $formMapper
                    // Every attribute that will be update need to be mapped here. Following attributes are manually inserted in element-import.html.twig, but we still need them here as hidden input
                    ->add('osmQueriesJson', HiddenType::class)
                    ->add('url', HiddenType::class)
                    ->add('sourceType', null, ['attr' => ['class' => 
                            'gogo-element-import',
                            'data-title-layer' => $this->config->getDefaultTileLayer()->getUrl(),
                            'data-default-bounds' => json_encode($this->config->getDefaultBounds()),
                        ], 'required' => true])
                ->end()
                ->panel('parameters')
                    ->add('refreshFrequencyInDays')
                    ->add('usersToNotify', ModelType::class, [
                        'class' => 'App\Document\User',
                        'multiple' => true,
                        'query' => $usersQuery,
                        'btn_add' => false,
                        ])
                    ->add('moderateElements', null, [
                        'disabled' => $isSynchronized,
                        'label_attr' => $isSynchronized ? ['style' => 'opacity:0.7; text-decoration:line-through;'] : [],
                        'data' => $isSynchronized ? false : $this->getSubject()->getModerateElements()
                    ])
                    ->add('contact')
                    ->add('idsToIgnore', ChoiceType::class,[
                        'choices' => $idsToIgnoreChoices,
                        'multiple' => true,
                        'required' => false,
                        'sortable' => true,
                    ])
                    ->add('idsToIgnore-clearAll', TextType::class, [
                        'label' => false,
                        'mapped' => false,
                        'attr' => ['class' => 'gogo-clear-all-button']
                    ]);
        } else {
            $formMapper                    
                    ->add('url', UrlType::class)
                    ->add('moderateElements');
        }
        $formMapper->end();

        if ($isDynamic && in_array($sourceType, ['osm', 'gogocarto'])) {

            $formMapper
                ->panel('interoperability')
                    ->add('permissionAlert', TextType::class, [
                        'mapped' => false,
                        'label' => false,
                        'attr' => ['class' => 'gogo-interoperability-permission-alert'],
                        'data' => [
                            'sourceType' => $sourceType,
                            'isReadyForSynchronization' => $isReadyForSynchronization
                        ]
                    ])
                    ->add('apiKey', !$isReadyForSynchronization || $sourceType !== 'gogocarto' ? HiddenType::class : null)
                    ->add('isSynchronized', !$isReadyForSynchronization ? HiddenType::class : null, [
                        'disabled' => !$isReadyForSynchronization,
                        'required' => false,
                        'help' => $this->trans('dynamic_imports.fields.isSynchronized_help_text', ['%sourceType%' => $sourceType])
                    ])
                    ->add('allowAdd', !$isReadyForSynchronization || $sourceType === 'gogocarto' ? HiddenType::class : null, [
                        'disabled' => !$isReadyForSynchronization,
                        'required' => false,
                    ])
            ;
                    
            if ($isReadyForSynchronization) {
                $formMapper
                    ->add('changesHistoryButton', !$isReadyForSynchronization ? HiddenType::class : TextType::class, [
                        'mapped' => false,
                        'label' => false,
                        'attr' => ['class' => 'gogo-interoperability-changes-history-button'],
                    ]);
            }

            $formMapper->end();
        }
        
        if ($isPersisted) {
            $formMapper->panel('historic')
                        ->add('currState', null, ['attr' => ['class' => 'gogo-display-logs'], 'label_attr' => ['style' => 'display: none'], 'mapped' => false])
                    ->end();
        }
        $formMapper->end();

        // TAB - Custom Code
        $formMapper->tab('customCode')
            ->panel('code')
                ->add('customCode', null, ['attr' => ['class' => 'gogo-code-editor', 'format' => 'php', 'height' => '500']])
            ->end()
        ->end();

        
        if ($isPersisted) {
            // TAB - Ontology Mapping
            $suffix = $this->getSubject()->getNewOntologyToMap() ? '<label class="label label-info">'.$this->trans('imports.form.groups.newFields').'</label>' : '';

            $formMapper
                ->tab('imports.form.groups.ontologyMappingTab', ['label_trans_params' => ['%suffix%' => $suffix ]])
                    ->panel('ontologyMappingPanel')
                        ->add('ontologyMapping', null, [
                            'label_attr' => ['style' => 'display:none'], 
                            'attr' => ['class' => 'gogo-mapping-ontology', 
                            'data-form-props' => $formProperties, 
                            'data-props' => $elementProperties]])
                    ->end();
                
                if ($this->getSubject()->getSourceType() != 'osm') {
                    $formMapper
                    ->panel('otherOptions', ['box_class' => 'box box-default'])
                        ->add('geocodeIfNecessary')
                        ->add('fieldToCheckElementHaveBeenUpdated')
                    ->end();
                }
                $formMapper->end();

            // TAB - Taxonomy Mapping
            if (count($this->getSubject()->getOntologyMapping()) > 0) {     
                $suffix = $this->getSubject()->getNewTaxonomyToMap() ? '<label class="label label-info">'.$this->trans('imports.form.groups.newCategories').'</label>' : '';
                
                $formMapper->tab('imports.form.groups.taxonomyMapping', ['label_trans_params' => ['%suffix%' => $suffix ]])
                    ->panel('taxonomyMapping2')
                        ->add('taxonomyMapping', null, ['label_attr' => ['style' => 'display:none'], 'attr' => ['class' => 'gogo-mapping-taxonomy', 'data-options' => $optionsList]])
                    ->end()

                    ->panel('otherOptions', ['box_class' => 'box box-default'])
                        ->add('optionsToAddToEachElement', ModelType::class, [
                            'class' => 'App\Document\Option',
                            'multiple' => true,
                            'btn_add' => false],
                            ['admin_code' => 'admin.options'])
                        ->add('needToHaveOptionsOtherThanTheOnesAddedToEachElements')
                        ->add('preventImportIfNoCategories')
                    ->end()
                ->end();
            }

            if ($this->getSubject()->isDynamicImport() && $this->getSubject()->getIsSynchronized()) {
                // TAB - Custom Code For Export
                $elementIdToTest = $dm->query('Element')->field('source')->references($this->getSubject())->getOne();
                $this->elementId = $elementIdToTest ? $elementIdToTest->getId() : null;
                $formMapper->tab('customCodeForExportTab')
                    ->panel( 'customCodeForExportPanel_' . $sourceType)
                        ->add('customCodeForExport', null, [
                            'attr' => ['class' => 'gogo-code-editor', 'format' => 'php', 'height' => '500']])
                        ->add('test', TextType::class, [
                            'mapped' => false,
                            'label' => false,
                            'attr' => ['class' => 'gogo-import-test-export']
                        ])
                    ->end()
                ->end();
            }
        }
    }

    protected function configureRoutes(RouteCollection $collection)
    {
        $collection->add('refresh', $this->getRouterIdParameter().'/refresh');
        $collection->add('collect', $this->getRouterIdParameter().'/collect');
        $collection->add('showData', $this->getRouterIdParameter().'/show-data');
        $collection->add('testElementExport', $this->getRouterIdParameter().'/show-data');
        $collection->add('delete-and-keep-elements', $this->getRouterIdParameter().'/delete-and-keep-elements');
    }

    protected function configureDatagridFilters(DatagridMapper $datagridMapper)
    {
        $datagridMapper
            ->add('sourceName')
        ;
    }

    public function createQuery($context = 'list')
    {
        $isDynamic = "App\Document\ImportDynamic" == $this->getClass();
        $query = parent::createQuery($context);
        if (!$isDynamic) {
            $query->field('type')->equals('normal');
        }
        $query->sort('updatedAt', 'DESC');

        return $query;
    }

    public function configureBatchActions($actions)
    {
        return [];
    }

    protected function configureListFields(ListMapper $listMapper)
    {
        $dm = GoGoHelper::getDmFromAdmin($this);
        $deletedElementsCount = $dm->get('Element')->findDeletedElementsByImportIdCount();
        $isDynamic = "App\Document\ImportDynamic" == $this->getClass();

        $listMapper
            ->addIdentifier('sourceName')
            ->add('logs', null, ['template' => 'admin/partials/import/list_total_count.html.twig']);
        if ($isDynamic) {
            $listMapper
            ->add('idsToIgnore', null, ['template' => 'admin/partials/import/list_non_visibles_count.html.twig', 'choices' => $deletedElementsCount])
            ->add('refreshFrequencyInDays', null, ['template' => 'admin/partials/import/list_refresh_frequency.html.twig']);
        }

        $listMapper
            ->add('lastRefresh', null, ['template' => 'admin/partials/import/list_last_refresh.html.twig'])
            ->add('_action', 'actions', [
                'actions' => [
                    'edit' => [],
                    'delete' => ['template' => 'admin/partials/import/list_action_delete_split_button.html.twig'],
                    'refresh' => ['template' => 'admin/partials/list__action_refresh.html.twig'],
                ],
            ])
        ;
    }
}
