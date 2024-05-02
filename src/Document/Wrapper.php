<?php

namespace App\Document;

use Doctrine\ODM\MongoDB\Mapping\Annotations as MongoDB;

/**
 * Wrapper.
 *
 * @MongoDB\Document(repositoryClass="App\Repository\WrapperRepository")
 */
class Wrapper
{
    /**
     * @var int
     *
     * @MongoDB\Id(strategy="INCREMENT")
     */
    private $id;

    /**
     * @var string
     *
     * @MongoDB\Field(type="string")
     */
    private $title;

    /**
     * @var string
     *
     * @MongoDB\Field(type="string")
     */
    private $content;

    /**
     * @var string
     *
     * @MongoDB\Field(type="string")
     */
    private $rawContent;

    /**
     * @var string
     *
     * @MongoDB\Field(type="string")
     */
    private $textColor;

    /**
     * @var string
     *
     * @MongoDB\Field(type="string")
     */
    private $backgroundColor;

    /**
     * @Gedmo\Mapping\Annotation\SortablePosition
     * @MongoDB\Field(type="int")
     */
    private $position;

    /**
     * Get id.
     *
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Set title.
     *
     * @param string $title
     *
     * @return Wrapper
     */
    public function setTitle($title)
    {
        $this->title = $title;

        return $this;
    }

    /**
     * Get title.
     *
     * @return string
     */
    public function getTitle()
    {
        return $this->title;
    }

    /**
     * Set content.
     *
     * @param string $content
     *
     * @return Wrapper
     */
    public function setContent($content)
    {
        $this->content = $content;

        return $this;
    }

    /**
     * Get content.
     *
     * @return string
     */
    public function getContent()
    {
        return $this->content;
    }

    /**
     * Set backgroundColor.
     *
     * @param string $backgroundColor
     *
     * @return Wrapper
     */
    public function setBackgroundColor($backgroundColor)
    {
        $this->backgroundColor = $backgroundColor;

        return $this;
    }

    /**
     * Get backgroundColor.
     *
     * @return string
     */
    public function getBackgroundColor()
    {
        return $this->backgroundColor;
    }

    /**
     * Set position.
     *
     * @param int $position
     *
     * @return $this
     */
    public function setPosition($position)
    {
        $this->position = $position;

        return $this;
    }

    /**
     * Get position.
     *
     * @return int $position
     */
    public function getPosition()
    {
        return $this->position;
    }

    /**
     * Set textColor.
     *
     * @param string $textColor
     *
     * @return $this
     */
    public function setTextColor($textColor)
    {
        $this->textColor = $textColor;

        return $this;
    }

    /**
     * Get textColor.
     *
     * @return string $textColor
     */
    public function getTextColor()
    {
        return $this->textColor;
    }

    /**
     * Set rawContent.
     *
     * @param string $rawContent
     *
     * @return $this
     */
    public function setRawContent($rawContent)
    {
        $this->rawContent = $rawContent;

        return $this;
    }

    /**
     * Get rawContent.
     *
     * @return string $rawContent
     */
    public function getRawContent()
    {
        return $this->rawContent;
    }
}
