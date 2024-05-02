<?php

namespace App\Services;

use App\Document\UserRoles;
use Doctrine\ODM\MongoDB\DocumentManager;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Contracts\Translation\TranslatorInterface;

class ChangesHistoryService
{
    private $dm;
    private $t;
  
    public function __construct(DocumentManager $dm, TranslatorInterface $t)
    {
        $this->dm = $dm;
        $this->t = $t;
    } 

    private function trans($key, $params = [])
    {
        return $this->t->trans($key, $params, 'admin');
    }
    
    public function getProjectChangesHistory($maxRows=50, $projectId=null, $exportMode=false)
    {
      $maxRows = $this->getChangesHistoryMaxRows($maxRows, $exportMode);
      
      $contributions = $this->dm->query('UserInteractionContribution');
      
      $project = null;
      if ($projectId) { 
        $project = $this->dm->get('AuthorizedProject')->find($projectId);
      }
      if ($project) {
        $contributions = $contributions->field('externalOperator')->equals($project->getUrl());
      } else {
        $contributions = $contributions->field('externalOperator')->exists(true);
      }
      
      $contributions = $contributions->sort('updatedAt', 'desc')
        ->limit($maxRows)
        ->execute();

      return [
        'object' => $project,
        'contributions' => $contributions
      ];
    }
    
    public function getImportChangesHistory($maxRows=50, $importId, $exportMode=false)
    {
      if (!$importId) {
        return false;
      }
      $import = $this->dm->get('ImportDynamic')->find($importId);
      if (!$import) {
          return false;
      }
      
      $maxRows = $this->getChangesHistoryMaxRows($maxRows, $exportMode);
      
      $contributions = $this->dm->query('UserInteractionContribution');

      $elements = $this->dm->query('Element')
        ->field('source.$id')->Equals($importId)
        ->execute();
      if (count($elements) > 0) {
        $elementIds = [];
        foreach($elements as $element) {
          $elementIds[] = $element->getID();
        }
        $contributions = $contributions
          ->field('userRole')->notEqual(strval(UserRoles::GoGoBot))
          ->field('element.$id')->in($elementIds);
      } else {
        $contributions = [];
      }
      
      if ($contributions !== []) {
        $contributions = $contributions->sort('updatedAt', 'desc')
          ->limit($maxRows)
          ->execute();
      }
      
      return [
        'object' => $import,
        'contributions' => $contributions
      ];
    }
    
    protected function getChangesHistoryMaxRows($maxRows, $exportMode) {
      if ($exportMode) {
        return 10000;
      }
      if (!$maxRows) {
        return 50;
      } elseif ($maxRows > 500) {
        return 500;
      } else {
        return $maxRows;
      }
    }

    public function getProjectChangesHistoryExport($id=null)
    {
      $response = $this->getProjectChangesHistory(null, $id, true);
      $contributions = $response['contributions'];
      return($this->getChangesHistoryExport($contributions));
    }
    
    public function getImportChangesHistoryExport($id=null)
    {
      $response = $this->getImportChangesHistory(null, $id, true);
      $contributions = $response['contributions'];
      return($this->getChangesHistoryExport($contributions, false));
    }
    
    public function getChangesHistoryExport($contributions, $externalOperator=true)
    {
      $header = [];
      if ($externalOperator) {
        $header[] = $this->trans('authorized_projects.export.externalOperator');
      }
      $header[] = $this->trans('authorized_projects.export.elementId');
      $header[] = $this->trans('authorized_projects.export.elementName');
      $header[] = $this->trans('authorized_projects.export.createdAt');
      $header[] = $this->trans('authorized_projects.export.updatedAt');
      $header[] = $this->trans('authorized_projects.export.valuesBeforeUpdate');
      $header[] = $this->trans('authorized_projects.export.valuesAfterUpdate');

      $rows[] = implode(';', $header);
      foreach ($contributions as $contribution) {
        $changeSetBefore = array_map(function ($value) {return $value[0];}, $contribution->getChangeSet());
        $changeSetBefore = json_encode($changeSetBefore);
        $changeSetBefore = str_replace(';', ' ', $changeSetBefore);
        $changeSetAfter = array_map(function ($value) {return $value[1];}, $contribution->getChangeSet());
        $changeSetAfter = json_encode($changeSetAfter);
        $changeSetAfter = str_replace(';', ' ', $changeSetAfter);
        $row = [];
        if ($externalOperator) {
          $row[] = $contribution->getExternalOperator();
        }
        $row[] = $contribution->getElement()->getID();
        $row[] = str_replace(';', ' ', $contribution->getElement()->getName());
        $row[] = $contribution->getCreatedAt()->format(\DateTime::ATOM);
        $row[] = $contribution->getUpdatedAt()->format(\DateTime::ATOM);
        $row[] = $changeSetBefore;
        $row[] = $changeSetAfter;
        $rows[] = implode(';', $row);
      }
      $content = implode("\n", $rows);

      $response = new Response($content);

      $disposition = $response->headers->makeDisposition(
        ResponseHeaderBag::DISPOSITION_ATTACHMENT,
        $this->trans('authorized_projects.export.fileName')
      );
      $response->headers->set('Content-Encoding', 'UTF-8');
      $response->headers->set('Content-Type', 'text/pdf; charset=UTF-8');
      $response->headers->set('Content-Disposition', $disposition);
      return $response;      
    }
}
