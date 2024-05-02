<?php

namespace App\Services;

use Doctrine\ODM\MongoDB\DocumentManager;

class InteroperabilityService
{
    protected $dm;
    protected $config;

    public function __construct(DocumentManager $dm)
    {
        $this->dm = $dm;
    }

    public function getConfig()
    {
        if (!$this->config) $this->config = $this->dm->get('Configuration')->findConfiguration();
        return $this->config;
    }

    public function getAuthorizedProjectPermission($url)
    {
      if (!$url) return false;
      
      $currentProjectHost = $_SERVER['BASE_PROTOCOL'] . '://' . $_SERVER['HTTP_HOST'];
      
      $streamContextOptions = ['http' => ['timeout' => 5]];
      $json = @file_get_contents($url . '/api/authorized-projects.json', false, getStreamContextOptions($streamContextOptions));
      
      if (!$json) return false;
      
      $authorizedProjects = json_decode($json);
      
      if (!$authorizedProjects) return false;

      foreach ( $authorizedProjects as $authorizedProject ) {
        if ( $currentProjectHost === $authorizedProject->url && $authorizedProject->isActivated ) {
            return true;
        }
      }

      return false;
    }
}
