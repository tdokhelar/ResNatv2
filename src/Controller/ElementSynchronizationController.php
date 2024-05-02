<?php

namespace App\Controller;

use App\Document\Coordinates;
use App\Services\ElementSynchronizationService;
use Doctrine\ODM\MongoDB\DocumentManager;
use Psr\Http\Message\ResponseInterface;
use GuzzleHttp\Exception\RequestException;
use Symfony\Component\HttpFoundation\Request;
use GuzzleHttp\Promise;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

class ElementSynchronizationController extends GoGoController
{
    public function sendToOsmAction(Request $request, $id,
                                    ElementSynchronizationService $synchService,
                                    DocumentManager $dm,
                                    SessionInterface $session)
    {
        $element = $dm->get('Element')->find($id);
        if (!$element->getGeo()->getLatitude()) {
            $this->addFlash('error', "Please geocode the item before sending to OSM");
            return $this->redirectToRoute('admin_app_element_showEdit', ['id' => $element->getId()]);
        }
        $data = $synchService->checkIfNewElementShouldBeAddedToOsm($element);
        if (!$data['result']) {
            $this->addFlash('error', "No OSM import has been found that correspond to this element");
            return $this->redirectToRoute('admin_app_element_showEdit', ['id' => $element->getId()]);
        }

        if ($request->getMethod() == 'POST') {
            $feature = $request->request->all();
            $feature['type'] = null;
            if ($feature['tags']['name']) $element->setName($feature['tags']['name']);
            $element->setGeo(new Coordinates($feature['center']['latitude'], $feature['center']['longitude']));
            $dm->persist($element);
            $dm->flush();

            $promise = $synchService->asyncDispatchToOSM($element, 'add', $feature)->then(
                function (ResponseInterface $res) {
                    if ($res->getStatusCode() == 200)
                        $this->addFlash('success', "Element successfully added to OSM");
                    else
                        $this->handleFailure($res->getReasonPhrase());
                },
                function (RequestException $e) {
                    $this->handleFailure($e->getMessage());
                }
            );
            Promise\Utils::settle([$promise])->wait();
            return $this->redirectToRoute('admin_app_element_showEdit', ['id' => $element->getId()]);
        } else {
            // Check duplicates on OSM
            if ($request->query->has('checkDuplicatesDone')) {
                $session->remove('duplicatesElements');
                $session->remove('redirectToIfNoDuplicate');
            } else if (count($data['duplicates']) > 0) {
                $session->set('duplicatesElements', $data['duplicates']);
                $session->set('redirectToIfNoDuplicate', $this->generateUrl('gogo_send_to_osm', ['id' => $element->getId(), 'checkDuplicatesDone' => true]));
                return $this->redirectToRoute('gogo_element_check_duplicate');
            }

            $feature = $synchService->elementToOsm($element);
            $config = $dm->get('Configuration')->findConfiguration();
            return $this->render('sync/send-to-osm.html.twig', [
                'element' => $element,
                'feature' => $feature,
                'config' => $config,
            ]);
        }
    }

    private function handleFailure($errorMessage)
    {
        $this->addFlash('error', $errorMessage);
    }
}