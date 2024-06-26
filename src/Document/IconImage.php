<?php

namespace App\Document;

use Doctrine\ODM\MongoDB\Mapping\Annotations as MongoDB;
use Vich\UploaderBundle\Mapping\Annotation as Vich;

/**
 * @MongoDB\Document
 * @Vich\Uploadable
 */
class IconImage extends Image
{
    /**
     * @var int
     *
     * @MongoDB\Id(strategy="INCREMENT")
     */
    private $id;

    protected $vichUploadFileKey = 'icon_image';
}