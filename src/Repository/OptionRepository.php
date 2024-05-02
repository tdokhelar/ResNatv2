<?php

namespace App\Repository;

use Datetime;
use Doctrine\ODM\MongoDB\Repository\DocumentRepository;

class OptionRepository extends DocumentRepository
{
    public function findOlderOptions(Datetime $datetime) {
      $qb = $this->query('Option');
      $qb->addOr($qb->expr()->field('createdAt')->equals(null));
      $qb->addOr($qb->expr()->field('createdAt')->lt($datetime));

      return $qb->execute();
    }
}
