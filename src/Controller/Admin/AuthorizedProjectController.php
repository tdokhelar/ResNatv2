<?php

namespace App\Controller\Admin;

use App\Services\ChangesHistoryService;
use Doctrine\ODM\MongoDB\DocumentManager;
use Sonata\AdminBundle\Controller\CRUDController as Controller;
use Symfony\Component\HttpFoundation\Request;


class AuthorizedProjectController extends Controller
{
    private $dm;
  
    public function __construct(DocumentManager $dm)
    {
        $this->dm = $dm;
    }

    public function newAPIKeyAction($id)
    {
      if (!$id) return $this->redirectToRoute('admin_app_authorizedproject_list');

      $authorizedProject = $this->dm->get('AuthorizedProject')->find($id);
      if ($authorizedProject) {
        $authorizedProject->generateApiKey();
        $this->dm->flush();
      }
      return $this->redirectToRoute('admin_app_authorizedproject_edit', ['id' => $id]);
    }
    
    public function changesHistoryAction(Request $request, $id=null, ChangesHistoryService $ChangesHistoryService)
    {
      $response = $ChangesHistoryService->getProjectChangesHistory($request->get('maxRows'), $id);
      if ($response) {
        return $this->renderWithExtraParams('admin/partials/show_changes_history.html.twig', [
          'object' => $response['object'],
          'contributions' => $response['contributions'],
          'action' => 'show',
          'backLink' => [
            'label' => $this->trans('authorized_projects.buttons.returnToList'),
            'url' => $this->generateUrl('admin_app_authorizedproject_list')
        ]], null);
      } else {
        $this->addFlash('error', 'error: no response to getProjectChangesHistory(id:' . $id . ')');
        return $this->redirectToRoute('admin_app_authorizedproject_list');
      }
    }

    public function exportChangesHistoryAction($id=null, ChangesHistoryService $ChangesHistoryService)
    {
      return $ChangesHistoryService->getProjectChangesHistoryExport($id);
    }

}