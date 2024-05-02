<?php
/**
 * @Author: Sebastian Castro
 * @Date:   2017-03-28 15:29:03
 * @Last Modified by:   Sebastian Castro
 * @Last Modified time: 2018-04-06 09:20:15
 */

namespace App\Admin\Element;

use App\Helper\GoGoHelper;
use App\Admin\GoGoAbstractAdmin;

class ElementAdminAbstract extends GoGoAbstractAdmin
{
    protected $datagridValues = [
      '_page' => 1,               // display the first page (default = 1)
      '_sort_order' => 'DESC',    // reverse order (default = 'ASC')
      '_sort_by' => 'updatedAt',  // name of the ordered field
                                  // (default = the model's id field, if any)
    ];

    protected $optionList;
    protected $optionsChoices = null;
    protected $flippedOptionsChoices = null;

    protected $session;

    public function initialize()
    {
        parent::initialize();
    }

    public function getOptionsChoices($flip=false)
    {
      if ($this->optionsChoices == null) {
        $dm = GoGoHelper::getDmFromAdmin($this);
        $rootCategories = $dm->get('Category')->findRootCategories();
        $optionsChoices = [];
        foreach ($rootCategories as $rootCategory) {
            $options = $rootCategory->getOptions();
            foreach ($options as $option) {
                $label = $option->__toString();
                $optionsChoices[$rootCategory->getName()][$option->getId()] = $label;
                $subOptions = $option->getChildrenOptions();
                foreach($subOptions as $subOption) {
                  $label = $subOption->__toString();
                  $optionsChoices[$rootCategory->getName()][$subOption->getId()] = $label;
                }
            }
        }
        $this->optionsChoices = $optionsChoices;
        // Formatting for admin filters
        $flippedOptionsChoices = $optionsChoices;
        foreach ($rootCategories as $rootCategory) {
          if(array_key_exists($rootCategory->getName(), $optionsChoices)) {
            $flippedOptionsChoices[$rootCategory->getName()] = array_flip($optionsChoices[$rootCategory->getName()]);
          }
        }
        $this->flippedOptionsChoices = $flippedOptionsChoices;
      }
      if($flip) {
        return $this->flippedOptionsChoices;
      } else {
        return $this->optionsChoices;
      }
    }

    public function setSession($session) {
      $this->session = $session;
    }
}
