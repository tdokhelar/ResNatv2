<?php

namespace App\Document;

use Doctrine\ODM\MongoDB\Mapping\Annotations as MongoDB;
use Gedmo\Mapping\Annotation as Gedmo;
use JMS\Serializer\Annotation\Accessor;
use JMS\Serializer\Annotation\Exclude;
use JMS\Serializer\Annotation\Groups;

/**
 * Option.
 *
 * @MongoDB\Document(repositoryClass="App\Repository\OptionRepository")
 * @MongoDB\Index(keys={"name"="text"})
 */
class Option
{
    /**
     * @var int
     * @Groups({"semantic"})
     * @MongoDB\Id(strategy="INCREMENT")
     */
    private $id;

    public $type = "Option";

    /**
     * @var date
     *
     * @MongoDB\Field(type="date") @MongoDB\Index
     * @Gedmo\Timestampable(on="create")
     * @Exclude
     */
    private $createdAt;

    /**
     * @var date
     *
     * @MongoDB\Field(type="date") @MongoDB\Index
     * @Gedmo\Timestampable(on="update")
     * @Exclude
     */
    private $updatedAt;
    
    /**
     * @var string
     * @Groups({"semantic"})
     * @MongoDB\Field(type="string")
     */
    private $customId;

    /**
     * @var string
     * @Groups({"semantic"})
     * @MongoDB\Field(type="string")
     */
    private $name = '';

    /**
     * @var string
     * @Groups({"semantic"})
     * @MongoDB\Field(type="string")
     */
    private $nameShort;

    /**
     * @Exclude
     * @MongoDB\ReferenceOne(targetDocument="App\Document\Category", inversedBy="options", cascade={"persist"})
     */
    public $parent;

    /**
     * @Accessor(getter="getParentId")
     */
    private $parentId;

    /**
     * @Groups({"semantic"})
     * @Accessor(getter="getNameWithAllParentOptions")
     */
    private $nameWithParent;

    /**
     * @var int
     * @MongoDB\Field(type="int")
     */
    private $index;

    /**
     * @var string
     * @Groups({"semantic"})
     * @MongoDB\Field(type="string")
     */
    private $color;

    /**
     * @var string
     * @MongoDB\Field(type="string")
     */
    private $softColor;

    /**
     * @var string
     * @Groups({"semantic"})
     * @Accessor(getter="getIconToDisplay")
     * @MongoDB\Field(type="string")
     */
    private $icon;

    /**
     * @var string
     *
     * @MongoDB\ReferenceOne(targetDocument="App\Document\IconImage", cascade={"all"})
     * @Exclude
     */
    private $iconFile;
    
    /**
     * @var string
     * @Groups({"semantic"})
     * @MongoDB\Field(type="string")
     */
    private $markerShape;
    
    /**
     * @var int
     * @Groups({"semantic"})
     * @MongoDB\Field(type="string")
     */
    private $markerSize;

    /**
     * @var string
     * @Groups({"semantic"})
     * @MongoDB\Field(type="string")
     */
    private $textHelper;

    /**
     * @var string
     * @Groups({"semantic"})
     * @MongoDB\Field(type="string")
     */
    private $url;

    /**
     * @var bool
     * @MongoDB\Field(type="boolean")
     */
    private $useIconForMarker = true;

    /**
     * @var bool
     * @MongoDB\Field(type="boolean")
     */
    private $useColorForMarker = true;

    /**
     * @var bool
     * @MongoDB\Field(type="boolean")
     */
    private $displayInMenu = true;

    /**
     * @var bool
     * @MongoDB\Field(type="boolean")
     */
    private $displayInInfoBar = true;

    /**
     * @var bool
     * @MongoDB\Field(type="boolean")
     */
    private $displayInForm = true;

    /**
     * @var bool
     * @MongoDB\Field(type="boolean")
     */
    private $displayChildrenInMenu = true;

    /**
     * @var bool
     * @MongoDB\Field(type="boolean")
     */
    private $displayChildrenInInfoBar = true;

    /**
     * @var bool
     * @MongoDB\Field(type="boolean")
     */
    private $displayChildrenInForm = true;

    /**
     * @var bool
     * @MongoDB\Field(type="boolean")
     */
    private $showExpanded = false;

    /**
     * @var bool
     * @MongoDB\Field(type="boolean")
     */
    private $unexpandable = false;

    /**
     * @var bool
     * @Exclude(if="object.getIsFixture() == false")
     * If Option is loaded by a fixture
     */
    private $isFixture = false;

    /**
     * @Accessor(getter="getOrderedSubcategories")
     * @MongoDB\ReferenceMany(targetDocument="App\Document\Category", mappedBy="parent",cascade={"persist", "remove"}, sort={"index"="ASC"})
     */
    private $subcategories;

    /** 
    * OpenStreetMap Tags : { amenity: shop, key: value }
    * @MongoDB\Field(type="hash") 
    */
    private $osmTags = [];

    /**
     * @var bool
     * @MongoDB\Field(type="boolean")
     */
    private $enableDescription = false;

    /**
     * @var string
     * @MongoDB\Field(type="string")
     */
    private $descriptionLabel = '';

    public function __construct()
    {
        $this->subcategories = new \Doctrine\Common\Collections\ArrayCollection();
    }

    public function __toString()
    {
        return $this->getNameWithAllParentOptions();
    }

    public function getIconToDisplay()
    {
        return $this->getIcon() ? $this->getIcon() : ($this->getIconFile() ? $this->getIconFile()->getUrl() : "");
    }

    public function allSubcategories()
    {
        $result = [];

        return $result;
    }
    
    public function getNameWithAllParentOptions()
    {
        return $this->recursivlyAddParentOptionName($this, $this->getName());
    }
    
    private function recursivlyAddParentOptionName($option, $nameWithAllParentOptions)
    {
        $parentOption = $option->getParentOption();
        if ($parentOption) {
            $parentName = $parentOption->getName();
            return $this->recursivlyAddParentOptionName($parentOption, $parentName . ' ↣ ' . $nameWithAllParentOptions);
        } else {
            return $nameWithAllParentOptions;
        }
    }

    public function getParentOption()
    {
        if (!$this->parent) {
            return null;
        }

        return $this->parent->parent;
    }

    public function getParentOptionId()
    {
        $parent = $this->getParentOption();

        return $parent ? $parent->getId() : null;
    }

    public function getIdAndParentOptionIds()
    {
        return $this->recursivelyAddParentOptionId($this);
    }

    private function recursivelyAddParentOptionId($option)
    {
        $result = [];
        $parentOption = $option->getParentOption();
        if ($parentOption) {
            $result = $this->recursivelyAddParentOptionId($parentOption);
        }
        $result[] = $option->getId();

        return $result;
    }

    public function getIdAndChildrenOptionIds()
    {
        return $this->recursivelyAddChildrenOptionIds($this);
    }

    private function recursivelyAddChildrenOptionIds($option)
    {
        $result = [$option->getId()];
        foreach ($option->getSubcategories() as $categorie) {
            foreach ($categorie->getOptions() as $childOption) {
                $result = array_merge($result, $this->recursivelyAddChildrenOptionIds($childOption));
            }
        }

        return $result;
    }
    
    public function getChildrenOptions()
    {
        return $this->recursivelyAddChildrenOptions($this);
    }
    
    private function recursivelyAddChildrenOptions($option)
    {
        $result = ($option === $this) ? [] : [$option];
        foreach ($option->getSubcategories() as $categorie) {
            foreach ($categorie->getOptions() as $childOption) {
                $result = array_merge($result, $this->recursivelyAddChildrenOptions($childOption));
            }
        }

        return $result;
    }

    public function getAllSubcategoriesIds()
    {
        return $this->recursivelyGetSubcategoriesIds($this);
    }

    private function recursivelyGetSubcategoriesIds($option)
    {
        $result = [];
        foreach ($option->getSubcategories() as $categorie) {
            $result[] = $categorie->getId();
            foreach ($categorie->getOptions() as $childOption) {
                $result = array_merge($result, $this->recursivelyGetSubcategoriesIds($childOption));
            }
        }

        return $result;
    }

    public function getSubcategoriesCount()
    {
        if ($this->subcategories) {
            return $this->subcategories->count();
        }

        return 0;
    }

    public function getOrderedSubcategories()
    {
        $sortedCategories = is_array($this->subcategories) ? $this->subcategories : $this->subcategories->toArray();
        usort($sortedCategories, function ($a, $b) { return $a->getIndex() - $b->getIndex(); });

        return $sortedCategories;
    }

    /**
     * Get id.
     *
     * @return int_id $id
     */
    public function getId()
    {
        return $this->id;
    }

    public function getCustomStringId()
    {
        return $this->customId ?: strval($this->id);
    }

    /**
     * Set created.
     *
     * @param date $created
     *
     * @return $this
     */
    public function setCreatedAt($createdAt)
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    /**
     * Get created.
     *
     * @return date $created
     */
    public function getCreatedAt()
    {
        return $this->createdAt ?? new \DateTime();
    }

    /**
     * Set name.
     *
     * @param string $name
     *
     * @return $this
     */
    public function setName($name)
    {
        $this->name = $name;

        return $this;
    }

    /**
     * Get name.
     *
     * @return string $name
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Set nameShort.
     *
     * @param string $nameShort
     *
     * @return $this
     */
    public function setNameShort($nameShort)
    {
        $this->nameShort = $nameShort;

        return $this;
    }

    /**
     * Get nameShort.
     *
     * @return string $nameShort
     */
    public function getNameShort()
    {
        return $this->nameShort;
    }

    /**
     * Add subcategory.
     *
     * @param App\Document\Category $subcategory
     */
    public function addSubcategory(\App\Document\Category $subcategory, $updateParent = true)
    {
        if ($updateParent) {
            $subcategory->setParent($this, false);
        }
        $this->subcategories[] = $subcategory;
    }

    /**
     * Remove subcategory.
     *
     * @param App\Document\Category $subcategory
     */
    public function removeSubcategory(\App\Document\Category $subcategory, $updateParent = true)
    {
        if ($updateParent) {
            $subcategory->setParent(null);
        }
        $this->subcategories->removeElement($subcategory);
    }

    /**
     * Get subcategories.
     *
     * @return \Doctrine\Common\Collections\Collection $subcategories
     */
    public function getSubcategories()
    {
        return $this->subcategories;
    }

    /**
     * Set index.
     *
     * @param int $index
     *
     * @return $this
     */
    public function setIndex($index)
    {
        $this->index = (int) $index;

        return $this;
    }

    /**
     * Get index.
     *
     * @return int $index
     */
    public function getIndex()
    {
        return (int) $this->index;
    }

    /**
     * Set color.
     *
     * @param string $color
     *
     * @return $this
     */
    public function setColor($color)
    {
        if (6 == strlen($color)) {
            $color = '#'.$color;
        }
        $this->color = $color;

        return $this;
    }

    /**
     * Get color.
     *
     * @return string $color
     */
    public function getColor()
    {
        return $this->color;
    }

    /**
     * Set color.
     *
     * @param string $color
     *
     * @return $this
     */
    public function setSoftColor($color)
    {
        if (6 == strlen($color)) {
            $color = '#'.$color;
        }
        $this->softColor = $color;

        return $this;
    }

    /**
     * Get color.
     *
     * @return string $color
     */
    public function getSoftColor()
    {
        return $this->softColor;
    }

    /**
     * Set icon.
     *
     * @param string $icon
     *
     * @return $this
     */
    public function setIcon($icon)
    {
        $this->icon = $icon;

        return $this;
    }

    /**
     * Get icon.
     *
     * @return string $icon
     */
    public function getIcon()
    {
        return $this->icon;
    }

    /**
     * Set markerShape.
     *
     * @param string $markerShape
     *
     * @return $this
     */
    public function setMarkerShape($markerShape)
    {
        $this->markerShape = $markerShape;

        return $this;
    }

    /**
     * Get markerShape.
     *
     * @return string $markerShape
     */
    public function getMarkerShape()
    {
        return $this->markerShape;
    }

    /**
     * Set markerSize.
     *
     * @param string $markerSize
     *
     * @return $this
     */
    public function setMarkerSize($markerSize)
    {
        $this->markerSize = $markerSize;

        return $this;
    }

    /**
     * Get markerSize.
     *
     * @return string $markerSize
     */
    public function getMarkerSize()
    {
        return $this->markerSize;
    }
    
    /**
     * Set textHelper.
     *
     * @param string $textHelper
     *
     * @return $this
     */
    public function setTextHelper($textHelper)
    {
        $this->textHelper = $textHelper;

        return $this;
    }

    /**
     * Get textHelper.
     *
     * @return string $textHelper
     */
    public function getTextHelper()
    {
        return $this->textHelper;
    }

    /**
     * Set useIconForMarker.
     *
     * @param bool $useIconForMarker
     *
     * @return $this
     */
    public function setUseIconForMarker($useIconForMarker)
    {
        $this->useIconForMarker = $useIconForMarker;

        return $this;
    }

    /**
     * Get useIconForMarker.
     *
     * @return bool $useIconForMarker
     */
    public function getUseIconForMarker()
    {
        return $this->useIconForMarker;
    }

    /**
     * Set useColorForMarker.
     *
     * @param bool $useColorForMarker
     *
     * @return $this
     */
    public function setUseColorForMarker($useColorForMarker)
    {
        $this->useColorForMarker = $useColorForMarker;

        return $this;
    }

    /**
     * Get useColorForMarker.
     *
     * @return bool $useColorForMarker
     */
    public function getUseColorForMarker()
    {
        return $this->useColorForMarker;
    }

    /**
     * Set showOpenHours.
     *
     * @param bool $showOpenHours
     *
     * @return $this
     */
    public function setShowOpenHours($showOpenHours)
    {
        $this->showOpenHours = $showOpenHours;

        return $this;
    }

    /**
     * Get showOpenHours.
     *
     * @return bool $showOpenHours
     */
    public function getShowOpenHours()
    {
        return $this->showOpenHours;
    }

    /**
     * Set showExpanded.
     *
     * @param bool $showExpanded
     *
     * @return $this
     */
    public function setShowExpanded($showExpanded)
    {
        $this->showExpanded = $showExpanded;

        return $this;
    }

    /**
     * Get showExpanded.
     *
     * @return bool $showExpanded
     */
    public function getShowExpanded()
    {
        return $this->showExpanded;
    }

    /**
     * Set parent.
     *
     * @param App\Document\Category $parent
     *
     * @return $this
     */
    public function setParent(\App\Document\Category $parent, $updateParent = true)
    {
        if ($parent && in_array($parent->getId(), $this->getAllSubcategoriesIds())) {
            // Circular reference
        } else {
            $this->parent = $parent;
        }

        return $this;
    }

    /**
     * Get parent.
     *
     * @return App\Document\Category $parent
     */
    public function getParent()
    {
        return $this->parent;
    }

    public function getParentId()
    {
        return $this->getParent() ? $this->getParent()->getId() : null;
    }

    /**
     * Set isFixture.
     *
     * @param bool $isFixture
     *
     * @return $this
     */
    public function setIsFixture($isFixture)
    {
        $this->isFixture = $isFixture;

        return $this;
    }

    /**
     * Get isFixture.
     *
     * @return bool $isFixture
     */
    public function getIsFixture()
    {
        return $this->isFixture;
    }

    /**
     * Set disableInInfoBar.
     *
     * @param bool $disableInInfoBar
     *
     * @return $this
     */
    public function setDisableInInfoBar($disableInInfoBar)
    {
        $this->disableInInfoBar = $disableInInfoBar;

        return $this;
    }

    /**
     * Get disableInInfoBar.
     *
     * @return bool $disableInInfoBar
     */
    public function getDisableInInfoBar()
    {
        return $this->disableInInfoBar;
    }

    /**
     * Set displayInMenu.
     *
     * @param bool $displayInMenu
     *
     * @return $this
     */
    public function setDisplayInMenu($displayInMenu)
    {
        $this->displayInMenu = $displayInMenu;

        return $this;
    }

    /**
     * Get displayInMenu.
     *
     * @return bool $displayInMenu
     */
    public function getDisplayInMenu()
    {
        return $this->displayInMenu;
    }

    /**
     * Set displayInInfoBar.
     *
     * @param bool $displayInInfoBar
     *
     * @return $this
     */
    public function setDisplayInInfoBar($displayInInfoBar)
    {
        $this->displayInInfoBar = $displayInInfoBar;

        return $this;
    }

    /**
     * Get displayInInfoBar.
     *
     * @return bool $displayInInfoBar
     */
    public function getDisplayInInfoBar()
    {
        return $this->displayInInfoBar;
    }

    /**
     * Set displayInForm.
     *
     * @param bool $displayInForm
     *
     * @return $this
     */
    public function setDisplayInForm($displayInForm)
    {
        $this->displayInForm = $displayInForm;

        return $this;
    }

    /**
     * Get displayInForm.
     *
     * @return bool $displayInForm
     */
    public function getDisplayInForm()
    {
        return $this->displayInForm;
    }

    /**
     * Set displayChildrenInMenu.
     *
     * @param bool $displayChildrenInMenu
     *
     * @return $this
     */
    public function setDisplayChildrenInMenu($displayChildrenInMenu)
    {
        $this->displayChildrenInMenu = $displayChildrenInMenu;

        return $this;
    }

    /**
     * Get displayChildrenInMenu.
     *
     * @return bool $displayChildrenInMenu
     */
    public function getDisplayChildrenInMenu()
    {
        return $this->displayChildrenInMenu;
    }

    /**
     * Set displayChildrenInInfoBar.
     *
     * @param bool $displayChildrenInInfoBar
     *
     * @return $this
     */
    public function setDisplayChildrenInInfoBar($displayChildrenInInfoBar)
    {
        $this->displayChildrenInInfoBar = $displayChildrenInInfoBar;

        return $this;
    }

    /**
     * Get displayChildrenInInfoBar.
     *
     * @return bool $displayChildrenInInfoBar
     */
    public function getDisplayChildrenInInfoBar()
    {
        return $this->displayChildrenInInfoBar;
    }

    /**
     * Set displayChildrenInForm.
     *
     * @param bool $displayChildrenInForm
     *
     * @return $this
     */
    public function setDisplayChildrenInForm($displayChildrenInForm)
    {
        $this->displayChildrenInForm = $displayChildrenInForm;

        return $this;
    }

    /**
     * Get displayChildrenInForm.
     *
     * @return bool $displayChildrenInForm
     */
    public function getDisplayChildrenInForm()
    {
        return $this->displayChildrenInForm;
    }

    /**
     * Set unexpandable.
     *
     * @param bool $unexpandable
     *
     * @return $this
     */
    public function setUnexpandable($unexpandable)
    {
        $this->unexpandable = $unexpandable;

        return $this;
    }

    /**
     * Get unexpandable.
     *
     * @return bool $unexpandable
     */
    public function getUnexpandable()
    {
        return $this->unexpandable;
    }

    /**
     * Set customId.
     *
     * @param string $customId
     *
     * @return $this
     */
    public function setCustomId($customId)
    {
        $this->customId = $customId;

        return $this;
    }

    /**
     * Get customId.
     *
     * @return string $customId
     */
    public function getCustomId()
    {
        return $this->customId;
    }

    /**
     * Set url.
     *
     * @param string $url
     *
     * @return $this
     */
    public function setUrl($url)
    {
        $this->url = $url;

        return $this;
    }

    /**
     * Get url.
     *
     * @return string $url
     */
    public function getUrl()
    {
        return $this->url;
    }

    /**
     * Get the value of osmTags
     */ 
    public function getOsmTags()
    {
        return $this->osmTags;
    }

    /**
     * Set the value of osmTags
     *
     * @return  self
     */ 
    public function setOsmTags($osmTags)
    {
        if (is_string($osmTags)) {
            $osmTags = (array) json_decode($osmTags);
        }
        $this->osmTags = $osmTags;
        return $this;
    }

    public function setOsmTag($key, $value)
    {
        $this->osmTags[$key] = $value;
        return $this;
    }

    public function getOsmTagsStringified() {
        $result = "";
        foreach($this->getOsmTags() as $key => $value) {
            $result .= "[$key=$value]";
        }
        return $result;
    }

    /**
     * Get the value of enableDescription
     *
     * @return  bool
     */ 
    public function getEnableDescription()
    {
        return $this->enableDescription;
    }

    /**
     * Set the value of enableDescription
     *
     * @param  bool  $enableDescription
     *
     * @return  self
     */ 
    public function setEnableDescription(bool $enableDescription)
    {
        $this->enableDescription = $enableDescription;

        return $this;
    }

    public function isDescriptionEnabled()
    {
        return $this->getEnableDescription() ? true : ($this->getParent() && $this->getParent()->getEnableDescription());
    }

    public function getOptionOrParentDescriptionLabel()
    {
        return $this->getDescriptionLabel() ? $this->getDescriptionLabel() : ($this->getParent() ? $this->getParent()->getDescriptionLabel() : '');
    }

    /**
     * Get the value of descriptionLabel
     *
     * @return  string
     */ 
    public function getDescriptionLabel()
    {
        return $this->descriptionLabel;
    }

    /**
     * Set the value of descriptionLabel
     *
     * @param  string  $descriptionLabel
     *
     * @return  self
     */ 
    public function setDescriptionLabel($descriptionLabel)
    {
        $this->descriptionLabel = $descriptionLabel;

        return $this;
    }

    /**
     * Get the value of iconFile
     *
     * @return App\Document\IconImage
     */ 
    public function getIconFile()
    {
        return $this->iconFile;
    }

    /**
     * Set the value of iconFile
     *
     * @param  App\Document\IconImage $iconFile
     *
     * @return  self
     */ 
    public function setIconFile($iconFile)
    {
        $this->iconFile = $iconFile;

        return $this;
    }

    /**
     * Get the value of updatedAt
     *
     * @return  date
     */
    public function getUpdatedAt()
    {
        return $this->updatedAt ?? $this->getCreatedAt();
    }

    /**
     * Set the value of updatedAt
     *
     * @param  date  $updatedAt
     *
     * @return  self
     */
    public function setUpdatedAt($updatedAt)
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }
}
