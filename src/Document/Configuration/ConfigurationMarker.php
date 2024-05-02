<?php

namespace App\Document\Configuration;

use Doctrine\ODM\MongoDB\Mapping\Annotations as MongoDB;
use App\Helper\GoGoHelper;

/** @MongoDB\EmbeddedDocument */
class ConfigurationMarker
{
    /** @MongoDB\Field(type="bool") */
    public $displayPopup = true;

    /** @MongoDB\Field(type="bool") */
    public $popupAlwaysVisible = false;

    /** @MongoDB\Field(type="string") */
    public $popupTemplate = '{{ name }}';

    /** @MongoDB\Field(type="bool") */
    public $popupTemplateUseMarkDown = false;

    /** @MongoDB\Field(type="hash") */
    public $fieldsUsedByTemplate = ['name'];

    /** @MongoDB\Field(type="bool") */
    public $useClusters = true;

    /** @MongoDB\Field(type="string") */
    public $defaultColor;

    /** @MongoDB\Field(type="string") */
    public $defaultIcon;

    /** @MongoDB\Field(type="string") */
    public $defaultShape = 'waterdrop';

    /** @MongoDB\Field(type="string") */
    public $defaultSize;

    // Those fields will be used in element compact Json
    public function updateFieldsUsedByTemplate()
    {
        $newFields = GoGoHelper::extractFieldsUsedInTemplate($this->popupTemplate);
        $oldFields = $this->fieldsUsedByTemplate;

        // if new fields different from old fields (order si not important)
        if (0 != count(array_diff(array_merge($newFields, $oldFields), array_intersect($newFields, $oldFields)))) {
            $this->setFieldsUsedByTemplate($newFields);
        }
    }

    /**
     * Set displayPopup.
     *
     * @param bool $displayPopup
     *
     * @return $this
     */
    public function setDisplayPopup($displayPopup)
    {
        $this->displayPopup = $displayPopup;

        return $this;
    }

    /**
     * Get displayPopup.
     *
     * @return bool $displayPopup
     */
    public function getDisplayPopup()
    {
        return $this->displayPopup;
    }

    /**
     * Set popupAlwaysVisible.
     *
     * @param bool $popupAlwaysVisible
     *
     * @return $this
     */
    public function setPopupAlwaysVisible($popupAlwaysVisible)
    {
        $this->popupAlwaysVisible = $popupAlwaysVisible;

        return $this;
    }

    /**
     * Get popupAlwaysVisible.
     *
     * @return bool $popupAlwaysVisible
     */
    public function getPopupAlwaysVisible()
    {
        return $this->popupAlwaysVisible;
    }

    /**
     * Set popupTemplate.
     *
     * @param string $popupTemplate
     *
     * @return $this
     */
    public function setPopupTemplate($popupTemplate)
    {
        $this->popupTemplate = $popupTemplate;
        $this->updateFieldsUsedByTemplate();

        return $this;
    }

    /**
     * Get popupTemplate.
     *
     * @return string $popupTemplate
     */
    public function getPopupTemplate()
    {
        return $this->popupTemplate;
    }

    /**
     * Set popupTemplateUseMarkDown.
     *
     * @param bool $popupTemplateUseMarkDown
     *
     * @return $this
     */
    public function setPopupTemplateUseMarkDown($popupTemplateUseMarkDown)
    {
        $this->popupTemplateUseMarkDown = $popupTemplateUseMarkDown;

        return $this;
    }

    /**
     * Get popupTemplateUseMarkDown.
     *
     * @return bool $popupTemplateUseMarkDown
     */
    public function getPopupTemplateUseMarkDown()
    {
        return $this->popupTemplateUseMarkDown;
    }

    /**
     * Set fieldsUsedByTemplate.
     *
     * @param hash $fieldsUsedByTemplate
     *
     * @return $this
     */
    public function setFieldsUsedByTemplate($fieldsUsedByTemplate)
    {
        // array_values : array need to be sequential for encoding properly with json_encode
        $this->fieldsUsedByTemplate = array_values($fieldsUsedByTemplate);
        return $this;
    }

    /**
     * Get fieldsUsedByTemplate.
     *
     * @return hash $fieldsUsedByTemplate
     */
    public function getFieldsUsedByTemplate()
    {
        return $this->displayPopup ? $this->fieldsUsedByTemplate : [];
    }

    /**
     * Set useClusters.
     *
     * @param bool $useClusters
     *
     * @return $this
     */
    public function setUseClusters($useClusters)
    {
        $this->useClusters = $useClusters;

        return $this;
    }

    /**
     * Get useClusters.
     *
     * @return bool $useClusters
     */
    public function getUseClusters()
    {
        return $this->useClusters;
    }

    /**
     * Get the value of defaultColor
     */ 
    public function getDefaultColor()
    {
        return $this->defaultColor;
    }

    /**
     * Set the value of defaultColor
     *
     * @return  self
     */ 
    public function setDefaultColor($defaultColor)
    {
        $this->defaultColor = $defaultColor;

        return $this;
    }

    /**
     * Get the value of defaultIcon
     */ 
    public function getDefaultIcon()
    {
        return $this->defaultIcon;
    }

    /**
     * Set the value of defaultIcon
     *
     * @return  self
     */ 
    public function setDefaultIcon($defaultIcon)
    {
        $this->defaultIcon = $defaultIcon;

        return $this;
    }

    /**
     * Get the value of defaultShape
     */ 
    public function getDefaultShape()
    {
        return $this->defaultShape;
    }

    /**
     * Set the value of defaultShape
     *
     * @return  self
     */ 
    public function setDefaultShape($defaultShape)
    {
        $this->defaultShape = $defaultShape;

        return $this;
    }

    /**
     * Get the value of defaultSize
     */ 
    public function getDefaultSize()
    {
        return $this->defaultSize;
    }

    /**
     * Set the value of defaultSize
     *
     * @return  self
     */ 
    public function setDefaultSize($defaultSize)
    {
        $this->defaultSize = $defaultSize;

        return $this;
    }
}
