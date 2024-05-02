<?php
/**
 * @Author: Sebastian Castro
 * @Date:   2017-03-28 15:29:03
 * @Last Modified by:   Sebastian Castro
 * @Last Modified time: 2018-04-25 12:11:11
 */

namespace App\Admin\Element;

// custom iterator
use Sonata\DoctrineMongoDBAdminBundle\Datagrid\ProxyQuery;
use App\Helper\GoGoHelper;
use App\Services\ElementExportService;

// There is a chain of inherance to split ElementAdmin in different files
// ElementAdminShowEdit inherit from ElementAdminList wich inherit from ElementAdminFilters and so on..
class ElementAdmin extends ElementAdminShowEdit
{
    
    protected $configurationExportId = null;
    
    public function getExportFields()
    {
        $dm = GoGoHelper::getDmFromAdmin($this);

        $configurationExport = null;
        $configurationExportArray = [];
        if ($this->configurationExportId) {
          $configurationExportArray = $dm->get('Configuration\ConfigurationExport')->findBy(['id'=>$this->configurationExportId]);  
        } else {
          $configurationExportArray = $dm->get('Configuration\ConfigurationExport')->findAll();
        }
        if (count($configurationExportArray) > 0) {
          $configurationExport = $configurationExportArray[0];
        }
          
        $elementExportService = new ElementExportService($dm);
        $exportFields = $elementExportService->getExportFields();
        
        // Remove comas in columns name for CSV
        // Actually it's not really needed because each value is wrapped into quotes ", but it could
        // help some people that does not know this option when trying to open the CSV
        $exportFields = array_flip($exportFields);
        $exportFields = str_replace(',', ' ', $exportFields);
        $exportFields = array_flip($exportFields);

        $selectedProperties = [];
        if ($configurationExport) {
          $selectedProperties = $configurationExport->getExportProperties();
        }
        if (count($selectedProperties) === 0) {
          $selectedExportFields = array_filter($exportFields, function ($exportFieldId) {
            return !startsWith($exportFieldId, 'gogo-option');
          });
        } else {        
          $selectedExportFields = array_filter($exportFields, function ($exportFieldId) use($selectedProperties) {
            return in_array($exportFieldId, $selectedProperties);
          });
        }

        return $selectedExportFields;
    }

    public function getDataSourceIterator()
    {
      if (array_key_exists("configurationExport", $_GET)) {
        $this->configurationExportId = $_GET["configurationExport"];
      }

      $datagrid = $this->getDatagrid();
      $datagrid->buildPager();
      $fields = [];

      foreach ($this->getExportFields() as $key => $field) {
        $label = $this->getTranslationLabel($field, 'export', 'label');
        $transLabel = $this->trans($label);

        // NEXT_MAJOR: Remove this hack, because all field labels will be translated with the major release
        // No translation key exists
        if ($transLabel == $label) {
            $fields[$key] = $field;
        } else {
            $fields[$transLabel] = $field;
        }
      }

      $query = $datagrid->getQuery();
      return new ElementSourceIterator($query instanceof ProxyQuery ? $query->getQuery() : $query, $fields);
    }
}
