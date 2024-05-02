<?php

namespace App\Repository;

use Datetime;
use Doctrine\ODM\MongoDB\Repository\DocumentRepository;

class TileLayerRepository extends DocumentRepository
{
    public function findOlderTileLayers(Datetime $datetime) {
      $qb = $this->query('TileLayer');
      $qb->addOr($qb->expr()->field('createdAt')->equals(null));
      $qb->addOr($qb->expr()->field('createdAt')->lt($datetime));

      return $qb->execute();
    }
}
