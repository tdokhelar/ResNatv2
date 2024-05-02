<?php
/**
 * @Author: Sebastian Castro
 * @Date:   2017-03-28 15:29:03
 * @Last Modified by:   Sebastian Castro
 * @Last Modified time: 2018-06-09 14:29:33
 */

namespace App\Admin\Element;

use Sonata\AdminBundle\Form\FormMapper;
use Sonata\AdminBundle\Show\ShowMapper;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use App\Form\ElementImageType;
use App\Form\ElementFileType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use App\Helper\GoGoHelper;
use App\Services\TaxonomyService;
use Sonata\AdminBundle\Form\Type\ModelAutocompleteType;

class ElementAdminShowEdit extends ElementAdminList
{
    public $config;
    public $dm;
    public $taxonomy;

    protected function configureFormFields(FormMapper $formMapper)
    {
        $this->dm = GoGoHelper::getDmFromAdmin($this);
        $this->config = $this->dm->get('Configuration')->findConfiguration();

        $container = $this->getConfigurationPool()->getContainer();
        $taxonomyService = $container->get(TaxonomyService::class);
        $this->taxonomy = $taxonomyService->getTaxonomyJson();

        $elementProperties = $this->dm->get('Element')->findDataCustomProperties();
        $elementProperties = array_values(array_diff($elementProperties, array_keys($this->getSubject()->getData())));

        $aggregationMode = $this->config->getDuplicates()->getDuplicatesByAggregation();
        $isAggregate = $aggregationMode && $this->subject->isAggregate();
        $aggregatedElementsDisplayClass = $isAggregate ? "" : "hidden"; // this field should always been in the admin othewise autocomplete do not work, so just hide it when not needed
        $subscribeFeatureDisplayClass = $this->config->getSubscribeFeature()->active ? "" : "hidden";

        $formMapper
          ->panel('aggregatedElements', ['box_class' => "box box-warning $aggregatedElementsDisplayClass"])
            ->add('aggregatedElements', ModelAutocompleteType::class, [
              'class' => 'App\Document\Element',
              'multiple' => true,
              'btn_add' => false,
              'property' => 'name',
              'label_attr'=> ['style'=> 'display:none'],
              'to_string_callback' => function($element, $property) {
                // hide agregates
                $sourceLabel = $element->getSourceKey() ? '(' . $element->getSourceKey() . ')' : '';
                return $element->isAggregate() ? "" : "{$element->getName()} {$sourceLabel}";
              },
            ])
          ->end()
          ->halfPanel('general')
            ->add('name', null, [
              'required' => true,
              'disabled' => $isAggregate
            ])
            ->add('optionValues', TextType::class, [
              'label_attr' => ['style' => 'display:none;'],
              'attr' => ['class' => 'gogo-element-taxonomy']])
            ->add('data', null, [
              'label_attr' => ['style' => 'display:none;'],
              'attr' => [
                'class' => 'gogo-element-data',
                'data-props' => json_encode($elementProperties)
              ]])
            ->add('userOwnerEmail', EmailType::class, [
              'disabled' => $isAggregate,  
            ])
            ->add('email', EmailType::class, [
              'disabled' => $isAggregate,  
            ])
            ->add('images', CollectionType::class, [
              'entry_type' => ElementImageType::class,
              'allow_add' => ! $isAggregate,
              'allow_delete' => ! $isAggregate,
            ])
            ->add('files', CollectionType::class, [
              'entry_type' => ElementFileType::class,
              'allow_add' => ! $isAggregate,
              'allow_delete' => ! $isAggregate,
            ])
            // ->add('openHours', OpenHoursType::class, ['required' => false])
          ->end()
          ->halfPanel('localisation')
            ->add('addressContainer', TextType::class, [
              'label_attr' => ['style' => 'display:none;'],
              'attr' => ['class' => 'gogo-element-address']
            ])
          ->end()
          ->halfPanel('subscribersList', ['box_class' => "box box-primary $subscribeFeatureDisplayClass"])
            ->add('subscriberEmails', CollectionType::class, [
              'label_attr' => ['style' => 'display:none;'],
              'entry_type' => EmailType::class,
              'allow_add' => ! $isAggregate,
              'allow_delete' => ! $isAggregate,
            ])
          ->end()
        ;       
    }

    protected function configureShowFields(ShowMapper $show)
    {
        if ($this->subject->getStatus() == -5) return; // modified pending version

        $show
          ->with('elements.form.groups.otherInfos', ['class' => 'col-md-6'])
            ->add('id')
            ->add('randomHash')
            ->add('editLink', null, [
              'label' => $this->t('elements.fields.editLink.label'),
              'template' => 'admin/edit/edit_element_edit_link.html.twig',
              'option' => [
                "id" => $this->subject->getId(),
                "email" =>  $this->subject->getEmail()
              ]
            ])
            ->add('oldId')
            ->add('sourceKey')
            ->add('createdAt', 'datetime', ['format' => $this->t('commons.date_time_format')])
            ->add('updatedAt', 'datetime', ['format' => $this->t('commons.date_time_format')])
          ->end();

        $show
          ->with('JSON', ['box_class' => 'box box-default'])
            ->add('compactJson')
            ->add('baseJson')
            ->add('adminJson')
          ->end();
    }
}