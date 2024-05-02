<?php

namespace App\Services;

use Doctrine\ODM\MongoDB\DocumentManager;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class ConfigurationService
{
    protected $dm;
    protected $securityContext;
    protected $config;
    protected $taxonomyService;

    /**
     * Constructor.
     */
    public function __construct(DocumentManager $dm, TokenStorageInterface $securityContext,
                                TaxonomyService $taxonomyService)
    {
        $this->dm = $dm;
        $this->taxonomyService = $taxonomyService;
        $this->securityContext = $securityContext;
    }

    public function isUserAllowed($featureName, $request = null)
    {
        $user = $this->securityContext->getToken()->getUser();

        if ('anon.' == $user) {
            $user = null;
        }

        $feature = $this->getFeatureConfig($featureName);

        // CHECK USER IS ALLOWED
        return $feature->isAllowed($user, $request ? $request->get('iframe') : false);
    }

    public function getConfig()
    {
        return $this->dm->get('Configuration')->findConfiguration();
    }

    public function getFeatureConfig($featureName)
    {
        if (!$this->getConfig()) return null;
        
        switch ($featureName) {
            case 'report':              $feature = $this->getConfig()->getReportFeature(); break;
            case 'add':                 $feature = $this->getConfig()->getAddFeature(); break;
            case 'edit':                $feature = $this->getConfig()->getEditFeature(); break;
            case 'directModeration':    $feature = $this->getConfig()->getDirectModerationFeature(); break;
            case 'delete':              $feature = $this->getConfig()->getDeleteFeature(); break;
            case 'vote':                $feature = $this->getConfig()->getCollaborativeModerationFeature(); break;
            case 'pending':             $feature = $this->getConfig()->getPendingFeature(); break;
        }

        return $feature;
    }

    public function getFullConfig()
    {
        $config = $this->getConfig();
        $config->hidePrivateInfos();
        
        $taxonomy = json_decode($this->taxonomyService->getTaxonomyJson());
        $itemsExportConfig = $this->dm->get('Configuration\ConfigurationExport')->findAll();
        
        return [
            'configuration'=> $config,
            'taxonomies' => $taxonomy,
            'itemsExportConfig' => $itemsExportConfig,
        ];
    }
}
