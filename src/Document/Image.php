<?php

namespace App\Document;

use Doctrine\ODM\MongoDB\Mapping\Annotations as MongoDB;
use Vich\UploaderBundle\Mapping\Annotation as Vich;

/*
* @MongoDB\Document
* @Vich\Uploadable
*/
class Image extends EmbeddedImage
{
    /**
     * @var int
     *
     * @MongoDB\Id(strategy="INCREMENT")
     */
    private $id;

    public function getId()
    {
        return $this->id;
    }
}
