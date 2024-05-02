<?php

namespace App\Services;

use Doctrine\ODM\MongoDB\DocumentManager;
use Symfony\Contracts\Translation\TranslatorInterface;

class TwigHelperService
{
  public function __construct(DocumentManager $dm, TranslatorInterface $t, $baseUrl, $useAsSaas)
  {
    $this->dm = $dm;
    $this->t = $t;
    $this->baseUrl = $baseUrl;
    $this->useAsSaas = $useAsSaas;
  }

  public function dm()
  {
    return $this->dm();
  }

  public function config()
  {
    return $this->dm->get('Configuration')->findConfiguration();
  }
  
  public function configurationExport()
  {
    return $this->dm->get('Configuration\ConfigurationExport')->findAll();
  }

  public function translator()
  {
    return $this->t;
  }

  public function mainUrl()
  {
    if ($url = $this->config()->getCustomDomain())
      return explode('://', $url)[1];
    elseif ($this->useAsSaas)
      return $this->config()->getDbName() . '.' . $this->baseUrl;
    else
      return $this->baseUrl;
  }

  public function listAbouts()
  {
    return $this->dm->get('About')->findAllOrderedByPosition();
  }

  public function countPartners()
  {
    return count($this->dm->get('Partner')->findAll());
  }

  public function findOption($id)
  {
    return $this->dm->get('Option')->find($id);
  }

  public function maxWebhookAttempts()
  {
    return WebhookService::MAX_ATTEMPTS;
  }
}