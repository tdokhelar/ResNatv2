<?php

namespace App\Controller\Admin;

use App\Controller\GoGoController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

class ElementAdminListController extends GoGoController
{
    public function saveColumnListAction(Request $request, SessionInterface $session)
    {
        $session->set('element-list-fields', $request->get('fields'));
        return $this->redirectToRoute('admin_app_element_list');
    }

}