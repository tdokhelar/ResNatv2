<?php
/**
 * @Author: Sebastian Castro
 * @Date:   2017-03-28 15:29:03
 * @Last Modified by:   Sebastian Castro
 * @Last Modified time: 2018-01-02 16:04:23
 */

namespace App\Admin\Element;

use App\Document\ElementStatus;
use App\Document\ModerationState;
use App\Helper\GoGoHelper;
use Sonata\AdminBundle\Datagrid\DatagridMapper;
use Sonata\AdminBundle\Form\Type\Operator\DateOperatorType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextType;

class ElementAdminFilters extends ElementAdminAbstract
{
    protected $formFields;
  
    public function buildDatagrid()
    {
        $this->persistFilters = true;
        parent::buildDatagrid();
    }

    protected function configureDatagridFilters(DatagridMapper $datagridMapper)
    {
      $dm = GoGoHelper::getDmFromAdmin($this);

      $datagridMapper
      ->add('name', 'doctrine_mongo_callback', $this->getStringOptions())
      ->add('status', 'doctrine_mongo_callback', $this->getSelectOptions(
        'select', 
        $this->getChoicesFromArray(
          'elements.fields.status_choices.', 
          ['-' => '', -7 => -7, -6 => -6,-4 => -4,-3 => -3,-2 => -2,-1 => -1,0 => 0,1 => 1,2 => 2,3 => 3,4 => 4,5 => 5,6 => 6,7 => 7, 8 => 8, 9 => 9]
        )
      ))
      ->add('valide', 'doctrine_mongo_callback', $this->getCheckboxOptions())
      ->add('pending', 'doctrine_mongo_callback', $this->getCheckboxOptions())
      ->add('moderationNeeded', 'doctrine_mongo_callback', $this->getCheckboxOptions())
      ->add('moderationState', 'doctrine_mongo_callback', $this->getSelectOptions(
        'select',
        $this->getChoicesFromArray(
          'elements.fields.moderationState_choices.',
          [-2 => -2,-1 => -1,0 => 0,1 => 1,2 => 2,3 => 3,4 => 4, 5 => 5, 6 => 6]
        )
      ))
      ->add('optionValuesAll', 'doctrine_mongo_callback', [
               'callback' => function ($queryBuilder, $alias, $field, $value) {
                   if (!$value || !$value['value']) {
                       return;
                   }
                   $queryBuilder->field('optionValues.optionId')->all($value['value']);

                   return true;
               },
                'field_type' => ChoiceType::class,
                'field_options' => [
                     'choices' => $this->getOptionsChoices(true),
                     'expanded' => false,
                     'multiple' => true,
                    ],
               ]
            )
      ->add('optionValuesIn', 'doctrine_mongo_callback', [
               'callback' => function ($queryBuilder, $alias, $field, $value) {
                   if (!$value || !$value['value']) {
                       return;
                   }
                   $queryBuilder->field('optionValues.optionId')->in($value['value']);

                   return true;
               },
                'field_type' => ChoiceType::class,
                'field_options' => [
                     'choices' => $this->getOptionsChoices(true),
                     'expanded' => false,
                     'multiple' => true,
                    ],
               ]
            )
      ->add('optionValuesNotIn', 'doctrine_mongo_callback', [
               'callback' => function ($queryBuilder, $alias, $field, $value) {
                   if (!$value || !$value['value']) {
                       return;
                   }
                   $queryBuilder->field('optionValues.optionId')->notIn($value['value']);

                   return true;
               },
                'field_type' => ChoiceType::class,
                'field_options' => [
                     'choices' => $this->getOptionsChoices(true),
                     'expanded' => false,
                     'multiple' => true,
                    ],
               ]
            )
      ->add('postalCode', 'doctrine_mongo_callback', [
                'callback' => function ($queryBuilder, $alias, $field, $value) {
                    if (!$value || !$value['value']) {
                        return;
                    }
                    $queryBuilder->field('address.postalCode')->equals($value['value']);

                    return true;
                },
            ])
      ->add('departementCode', 'doctrine_mongo_callback', [
                'callback' => function ($queryBuilder, $alias, $field, $value) {
                    if (!$value || !$value['value']) {
                        return;
                    }
                    $queryBuilder->field('address.postalCode')->equals(new \MongoRegex('/^'.$value['value'].'/'));

                    return true;
                },
            ])
      ->add('email', 'doctrine_mongo_callback', $this->getStringOptions())
      ->add('sourceKey', 'doctrine_mongo_callback', $this->getStringOptions())
      ->add('updatedAt', 'doctrine_mongo_callback', $this->getDateOptions())
      ->add('createdAt', 'doctrine_mongo_callback', $this->getDateOptions());
      

      // CUSTOM DATA
      $dataArray = $dm->get('Element')->findDataCustomProperties();
      // Some old database have fields with name containing illegal character for Symfony
      // for example a field with name "circonférence". Symfony is not happy with that
      // when creating the form, so we exclude them from the custom filters
      $dataArray = array_filter($dataArray, function($fieldName) {
        return $fieldName == slugify($fieldName, false);
      });
      $dataArray = array_unique($dataArray);
      sort($dataArray);

      // unset end date of date ranges
      foreach($dataArray as $data) {
        $formFieldType = $this->getFormFieldType($data);
        if ( $formFieldType
          && $formFieldType['type'] === 'date'
          && array_key_exists('range', $formFieldType)
          && $formFieldType['range'] === true
        ) {
          $key = array_search ($data . '_end', $dataArray);
          if ($key) {
            unset($dataArray[$key]);
          }
        }
      }
      
      foreach($dataArray as $data) {
        if (!$this->isCoreFilter($data)) {
          $formFieldType = $this->getFormFieldType($data);
          
          $value['data'] = $data;
          
          // -----------------------------------------------------------
          if ($formFieldType && $formFieldType['type'] === 'checkbox') {
          // -----------------------------------------------------------
            $datagridMapper->add($data, 'doctrine_mongo_callback', $this->getCheckboxOptions());
            continue;
          }
          // -----------------------------------------------------------
          if ($formFieldType && $formFieldType['type'] === 'date') {
          // -----------------------------------------------------------
            $isDateTime = array_key_exists('timepicker', $formFieldType) && $formFieldType['timepicker'] === true;
            $isRange = array_key_exists('range', $formFieldType) && $formFieldType['range'] === true;
            $datagridMapper->add($data, 'doctrine_mongo_callback', $this->getDateOptions($isDateTime, $isRange));
            continue;
          }
          if ($formFieldType && in_array($formFieldType['type'], ['radio-group', 'checkbox-group', 'select'])) {
          // -----------------------------------------------------------
            $valueChoices = [];
            foreach($formFieldType['values'] as $formFieldTypeValue) {
              $valueChoices[$formFieldTypeValue->label] = $formFieldTypeValue->value;
            }
            $datagridMapper->add($data, 'doctrine_mongo_callback', $this->getSelectOptions($formFieldType['type'], $valueChoices));
            continue;
          }
          // -----------------------------------------------------------
          if ($formFieldType && in_array($formFieldType['type'], ['elements'])) {
          // -----------------------------------------------------------
          
            if (!defined('CUSTOM_DATA_OPERATOR_TYPE_ALL'))          define('CUSTOM_DATA_OPERATOR_TYPE_ALL',           1);
            if (!defined('CUSTOM_DATA_OPERATOR_TYPE_AT_LEAST_ONE')) define('CUSTOM_DATA_OPERATOR_TYPE_AT_LEAST_ONE',  2);
            if (!defined('CUSTOM_DATA_OPERATOR_TYPE_NONE'))         define('CUSTOM_DATA_OPERATOR_TYPE_NONE',          3);
            
            $isMultiple = array_key_exists('multiple', $formFieldType) && $formFieldType['multiple'] === true;

            $elementChoices = $dm->query('Element')->field('data.' . $data)->exists(true)->execute();
            $valueChoices=[];
            foreach($elementChoices as $elementChoice) {
              $customPropertyValues = $elementChoice->getCustomProperty($data);
              if (is_array($customPropertyValues)) {
                foreach($customPropertyValues as $customPropertykey => $customProperty) {
                  $valueChoices["{$customPropertykey} - {$customProperty}"] = $customPropertykey;
                }
              }
            }
          
            $datagridMapper->add($data, 'doctrine_mongo_callback', [
              'callback' => function ($queryBuilder, $alias, $field, $value) {

                if (!$this->checkCallbackValue($value)) {
                  return;
                }

                $selectedValues = [];
                if (! is_Array($value['value'])) {
                  $selectedValues[] = $value['value'];
                } else {
                  $selectedValues = $value['value'];
                }

                $operator = CUSTOM_DATA_OPERATOR_TYPE_ALL;
                if ($value['type']) {
                  $operator = $value['type'];
                }

                $queryBuilder->field('data')->exists(true);
                switch($operator) {
                  case CUSTOM_DATA_OPERATOR_TYPE_ALL: 
                    foreach($selectedValues as $selectedValue) {
                      $queryBuilder->field('data.' . $field . '.' . $selectedValue)->exists(true);
                    }
                    break;
                  case CUSTOM_DATA_OPERATOR_TYPE_AT_LEAST_ONE: 
                    foreach($selectedValues as $selectedValue) {
                      $queryBuilder->addOr($queryBuilder->expr()->field('data.' . $field . '.' . $selectedValue)->exists(true));
                    }
                    break;
                  case CUSTOM_DATA_OPERATOR_TYPE_NONE: 
                    foreach($selectedValues as $selectedValue) {
                      $queryBuilder->field('data.' . $field . '.' . $selectedValue)->exists(false);
                    }
                    break;
                }
                return true;
              },
              'field_type' => ChoiceType::class,
              'field_options' => [
                'choices' => $valueChoices,
                'placeholder' => false,
                'expanded' => false,
                'multiple' => $isMultiple,
                'label_attr' => ['class' => '']
              ],
              'operator_type' => ChoiceType::class ,
              'operator_options' => $isMultiple
                ? ['choices' => [
                    $this->trans('elements.filter.choices.all') => CUSTOM_DATA_OPERATOR_TYPE_ALL,
                    $this->trans('elements.filter.choices.atLeastOne') => CUSTOM_DATA_OPERATOR_TYPE_AT_LEAST_ONE,
                    $this->trans('elements.filter.choices.none') => CUSTOM_DATA_OPERATOR_TYPE_NONE,
                  ]]
                : ['choices' => [
                    $this->trans('elements.filter.choices.contains') => CUSTOM_DATA_OPERATOR_TYPE_ALL,
                    $this->trans('elements.filter.choices.notContains') => CUSTOM_DATA_OPERATOR_TYPE_NONE,
                  ]]
            ]);
            continue;
          }
          
          // -----------------------------------------------------------
          // other formFieldTypes or no formFieldType
          // -----------------------------------------------------------
          $datagridMapper->add($data, 'doctrine_mongo_callback', $this->getStringOptions(true));

        }
        
      }
    }


    protected function isCoreFilter($field) {
      return in_array($field, [
        'name',
        'status',
        'valide',
        'pending',
        'moderationNeeded', 
        'moderationState',
        'optionValuesAll',
        'optionValuesIn',
        'optionValuesNotIn',
        'postalCode',
        'departementCode',
        'email',
        'sourceKey',
        'updatedAt',
        'createdAt'
      ]);
    }


    protected function getFormFieldType($data) {

      if (!$this->formFields) {
        $dm = GoGoHelper::getDmFromAdmin($this);
        $config = $dm->get('Configuration')->findConfiguration();
        $formFields = $config->getElementFormFields();

        // add reversedBy fields for field of type "elements"
        $formFieldsNames = array_filter($formFields, function($formField) {return property_exists($formField, 'name');});
        $formFieldsNames = array_map(function($formField) {return $formField->name;}, $formFieldsNames);
        $reversedByFields = [];
        foreach($formFields as $formField) {
          $field = (array) $formField;
          if (array_key_exists('reversedBy', $field) && $field['reversedBy'] && !in_array($field['reversedBy'], $formFieldsNames) ) {
            $reversedByField = $field;
            $reversedByField['name'] = $field['reversedBy'];
            $reversedByField['label'] = $field['reversedBy'];
            $reversedByField['reversedBy'] = $field['name'];
            $reversedByField['multiple'] = true;
            $reversedByFields[] = $reversedByField;
          }
        }
        $this->formFields = array_merge($formFields, $reversedByFields);
      }

      foreach($this->formFields as $formField) {
        $field = (array) $formField;
        if (array_key_exists('name', $field) && $data === $field['name']) {
          return ($field);
        }
      }
      
      return false;
    }

    
    protected function getChoicesFromArray($labels, $array) {
      return array_flip(array_map(function($value) use ($labels) {
        return $this->t($labels . $value);
      }, $array));
    }


    protected function getDateOptions($isDateTime=false, $isRange=false) {
      return [
        'callback' => function ($queryBuilder, $alias, $field, $value) use ($isDateTime, $isRange) {

          if (!$this->checkCallbackValue($value)) {
            return;
          }

          $operator = DateOperatorType::TYPE_EQUAL;
          if ($value['type']) {
            $operator = $value['type'];
          }
          
          $date = $value['value'];
          $endOfDay = clone $date;
          $endOfDay->add(new \DateInterval('P1D'));
          $endOfDay->sub(new \DateInterval('PT1S'));

          if (!$this->isCoreFilter($field)) {
            $field = 'data.' . $field;
            $date = $date->format(\DateTime::ATOM);
            $endOfDay = $endOfDay->format(\DateTime::ATOM);
            $queryBuilder->field('data')->exists(true);
          }

          $queryBuilder->field($field)->notEqual('');
          switch($operator) {
            case DateOperatorType::TYPE_GREATER_EQUAL: 
              $queryBuilder->field($field . ($isRange ? '_end' : ''))->gte($date);
              break;
            case DateOperatorType::TYPE_GREATER_THAN: 
              $queryBuilder->field($field)->gt($isDateTime ? $date : $endOfDay);
              break;
            case DateOperatorType::TYPE_EQUAL:
              if ($isRange) {
                $queryBuilder->field($field . '_end')->gte($date);
                $queryBuilder->field($field)->lte($date);
              } else {
                if ($isDateTime) {
                  $queryBuilder->field($field)->equals($date);
                } else {
                  $queryBuilder->field($field)->gte($date);
                  $queryBuilder->field($field)->lte($endOfDay);
                }
              }
              break;
            case DateOperatorType::TYPE_LESS_EQUAL: 
              $queryBuilder->field($field)->lte($isDateTime ? $date : $endOfDay);
              break;
            case DateOperatorType::TYPE_LESS_THAN: 
              $queryBuilder->field($field . ($isRange ? '_end' : ''))->lt($date);
              break;
          }
          return true;
        },
        'field_type' => $isDateTime ? DateTimeType::class : DateType::class,
        'field_options' => [
          'attr' => ['class' => $isDateTime ? 'datetime-field-filter' : 'date-field-filter'],
          'placeholder' => [
            'year' => $this->trans('elements.filter.placeholders.year'),
            'month' => $this->trans('elements.filter.placeholders.month'),
            'day' => $this->trans('elements.filter.placeholders.day'),
            'hour' => $this->trans('elements.filter.placeholders.hour'),
            'minute' => $this->trans('elements.filter.placeholders.minute'),
          ],
          'years' => range(1900, 2100)
        ],
        'operator_type' => ChoiceType::class,
        'operator_options' => ['choices' => [
          '>=' => DateOperatorType::TYPE_GREATER_EQUAL,
          '>' => DateOperatorType::TYPE_GREATER_THAN,
          '=' => DateOperatorType::TYPE_EQUAL,
          '<=' => DateOperatorType::TYPE_LESS_EQUAL,
          '<' => DateOperatorType::TYPE_LESS_THAN,
        ]],
      ];
    }


    protected function getCheckboxOptions() {
      return [
        'callback' => function ($queryBuilder, $alias, $field, $value) {

          if (!$this->checkCallbackValue($value)) {
            return;
          }
          
          if (!$this->isCoreFilter($field)) {
            $field = 'data.' . $field;
            $queryBuilder->field('data')->exists(true);
          }
          
          switch ($field) {
            case 'valide': 
              if ($value['value']) {
                $queryBuilder->field('status')->gt(ElementStatus::PendingAdd);
              } else {
                $queryBuilder->field('status')->lte(ElementStatus::PendingAdd);
              }
              break;
            case 'pending':
              if ($value['value']) {
                $queryBuilder->field('status')->in([ElementStatus::PendingModification, ElementStatus::PendingAdd]);
              } else {
                $queryBuilder->field('status')->notIn([ElementStatus::PendingModification, ElementStatus::PendingAdd]);
              }
              break;
            case 'moderationNeeded':
              if ($value['value']) {
                $queryBuilder->field('moderationState')->notIn([ModerationState::NotNeeded, ModerationState::PotentialDuplicate]);
                $queryBuilder->field('status')->gte(ElementStatus::PendingModification);
              } else {
                $queryBuilder->addOr($queryBuilder->expr()->field('moderationState')->in([ModerationState::NotNeeded, ModerationState::PotentialDuplicate]));
                $queryBuilder->addOr($queryBuilder->expr()->field('status')->lt(ElementStatus::PendingModification));

              }
              break;
            default:
              if ($value['value']) {
                $queryBuilder->field($field)->exists(true);
                $queryBuilder->field($field)->notEqual('');
              } else {
                $queryBuilder->addOr($queryBuilder->expr()->field($field)->exists(false));
                $queryBuilder->addOr($queryBuilder->expr()->field($field)->equals(''));
              }
              break;
          }
          return true;
        },
        'field_type' => ChoiceType::class,
        'field_options' => [
          'choices' => [
            $this->trans('elements.filter.choices.yes') => 1,
            $this->trans('elements.filter.choices.no') => 0
          ],
          'placeholder' => false,
          'expanded' => true,
          'attr' => ['class' => 'checkbox-field-filter']
        ]
      ];
    }
    
    
    protected function getSelectOptions($formFieldType, $valueChoices=[]) {

      if (!defined('CUSTOM_DATA_OPERATOR_TYPE_ALL'))          define('CUSTOM_DATA_OPERATOR_TYPE_ALL',           1);
      if (!defined('CUSTOM_DATA_OPERATOR_TYPE_AT_LEAST_ONE')) define('CUSTOM_DATA_OPERATOR_TYPE_AT_LEAST_ONE',  2);
      if (!defined('CUSTOM_DATA_OPERATOR_TYPE_NONE'))         define('CUSTOM_DATA_OPERATOR_TYPE_NONE',          3);
      
      $isMultiple = $formFieldType === 'checkbox-group';
    
      return [
        'callback' => function ($queryBuilder, $alias, $field, $value) {
          
          if (!$this->checkCallbackValue($value)) {
            return;
          }
          
          // '' value management
          if ($field === 'status' && $value['value'] === '-') {
            $value['value'] = '';
          }

          $selectedValues = [];
          if (! is_Array($value['value'])) {
            $selectedValues[] = $value['value'];
          } else {
            $selectedValues = $value['value'];
          }

          $operator = CUSTOM_DATA_OPERATOR_TYPE_ALL;
          if ($value['type']) {
            $operator = $value['type'];
          }
          
          if (!$this->isCoreFilter($field)) {
            $field = 'data.' . $field;
            $queryBuilder->field('data')->exists(true);
          }

          switch($operator) {
            case CUSTOM_DATA_OPERATOR_TYPE_ALL: 
              $queryBuilder->field($field)->all($selectedValues);
              break;
            case CUSTOM_DATA_OPERATOR_TYPE_AT_LEAST_ONE: 
              $queryBuilder->field($field)->in($selectedValues);
              break;
            case CUSTOM_DATA_OPERATOR_TYPE_NONE: 
              $queryBuilder->field($field)->notIn($selectedValues);
              break;
          }
          return true;
        },
        'field_type' => ChoiceType::class,
        'field_options' => [
          'choices' => $valueChoices,
          'placeholder' => false,
          'expanded' => false,
          'multiple' => $isMultiple,
          'label_attr' => ['class' => '']
        ],
        'operator_type' => ChoiceType::class ,
        'operator_options' => $isMultiple
          ? ['choices' => [
              $this->trans('elements.filter.choices.all') => CUSTOM_DATA_OPERATOR_TYPE_ALL,
              $this->trans('elements.filter.choices.atLeastOne') => CUSTOM_DATA_OPERATOR_TYPE_AT_LEAST_ONE,
              $this->trans('elements.filter.choices.none') => CUSTOM_DATA_OPERATOR_TYPE_NONE,
            ]]
          : ['choices' => [
              $this->trans('elements.filter.choices.equal') => CUSTOM_DATA_OPERATOR_TYPE_ALL,
              $this->trans('elements.filter.choices.notEqual') => CUSTOM_DATA_OPERATOR_TYPE_NONE,
            ]]
      ];
    }
    
    
    protected function getStringOptions() {

      // https://github.com/sonata-project/SonataAdminBundle/tree/4.x/src/Form/Type/Operator
      if (!defined('CUSTOM_DATA_OPERATOR_TYPE_CONTAINS'))     define('CUSTOM_DATA_OPERATOR_TYPE_CONTAINS',      1);
      if (!defined('CUSTOM_DATA_OPERATOR_TYPE_NOT_CONTAINS')) define('CUSTOM_DATA_OPERATOR_TYPE_NOT_CONTAINS',  2);
      if (!defined('CUSTOM_DATA_OPERATOR_TYPE_EQUAL'))        define('CUSTOM_DATA_OPERATOR_TYPE_EQUAL',         3);
      if (!defined('CUSTOM_DATA_OPERATOR_TYPE_STARTS_WITH'))  define('CUSTOM_DATA_OPERATOR_TYPE_STARTS_WITH',   4);
      if (!defined('CUSTOM_DATA_OPERATOR_TYPE_ENDS_WITH'))    define('CUSTOM_DATA_OPERATOR_TYPE_ENDS_WITH',     5);
      if (!defined('CUSTOM_DATA_OPERATOR_TYPE_NOT_EQUAL'))    define('CUSTOM_DATA_OPERATOR_TYPE_NOT_EQUAL',     6);

      return [
        'callback' => function ($queryBuilder, $alias, $field, $value) {

          if (!$this->checkCallbackValue($value)) {
            return;
          }

          $operator = CUSTOM_DATA_OPERATOR_TYPE_CONTAINS;
          if ($value['type']) {
            $operator = $value['type'];
          }
          
          $selectedValue = $value['value'];
          $selectedValueAsRegExp = preg_quote(strtolower($selectedValue));
          
          if (!$this->isCoreFilter($field)) {
            $field = 'data.' . $field;
            $queryBuilder->field('data')->exists(true);
          }

          switch($operator) {
            case CUSTOM_DATA_OPERATOR_TYPE_CONTAINS: 
              $queryBuilder->field($field)->where("this.$field && this.$field.toString().toLowerCase().match(/$selectedValueAsRegExp/)");
              break;
            case CUSTOM_DATA_OPERATOR_TYPE_NOT_CONTAINS:
              $queryBuilder->field($field)->where("!this.$field || !this.$field.toString().toLowerCase().match(/$selectedValueAsRegExp/)");
              break;
            case CUSTOM_DATA_OPERATOR_TYPE_EQUAL:
              $queryBuilder->field($field)->where("this.$field && this.$field.toString().toLowerCase().match(/^$selectedValueAsRegExp$/)");
              break;
            case CUSTOM_DATA_OPERATOR_TYPE_NOT_EQUAL:
              $queryBuilder->field($field)->where("!this.$field || !this.$field.toString().toLowerCase().match(/^$selectedValueAsRegExp$/)");
              break;
            case CUSTOM_DATA_OPERATOR_TYPE_STARTS_WITH:
              $queryBuilder->field($field)->where("this.$field && this.$field.toString().toLowerCase().match(/^$selectedValueAsRegExp/)");
              break;
            case CUSTOM_DATA_OPERATOR_TYPE_ENDS_WITH:
              $queryBuilder->field($field)->where("this.$field && this.$field.toString().toLowerCase().match(/$selectedValueAsRegExp$/)");
              break;
          }
          return true;
        },
        'field_type' =>  TextType::class,
        'operator_type' => ChoiceType::class,
        'operator_options' => ['choices' => [
          $this->trans('elements.filter.choices.equal') => CUSTOM_DATA_OPERATOR_TYPE_EQUAL,
          $this->trans('elements.filter.choices.notEqual') => CUSTOM_DATA_OPERATOR_TYPE_NOT_EQUAL,
          $this->trans('elements.filter.choices.contains') => CUSTOM_DATA_OPERATOR_TYPE_CONTAINS,
          $this->trans('elements.filter.choices.notContains') => CUSTOM_DATA_OPERATOR_TYPE_NOT_CONTAINS,
          $this->trans('elements.filter.choices.startsWith') => CUSTOM_DATA_OPERATOR_TYPE_STARTS_WITH,
          $this->trans('elements.filter.choices.endsWith') => CUSTOM_DATA_OPERATOR_TYPE_ENDS_WITH,
        ]],
      ];
    }
    
    protected function checkCallbackValue($value) {
      return $value && array_key_exists('value', $value) && ($value['value'] || $value['value'] === 0);
    }
}
