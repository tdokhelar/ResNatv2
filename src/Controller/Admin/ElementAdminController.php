<?php

namespace App\Controller\Admin;

use App\Services\ValidationType;
use App\Document\PostalAddress;
use App\Document\Coordinates;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

// Split this big controller into two classes
class ElementAdminController extends ElementAdminBulkController
{
    public function redirectEditAction()
    {
        $object = $this->admin->getSubject();

        if (!$object) {
            throw new NotFoundHttpException(sprintf('unable to find the object with id : %s', $id));
        }

        return $this->redirectToRoute('gogo_element_edit', ['id' => $object->getId()]);
    }

    public function redirectShowAction()
    {
        $object = $this->admin->getSubject();

        if (!$object) {
            throw new NotFoundHttpException(sprintf('unable to find the object with id : %s', $id));
        }

        return $this->redirect($object->getShowUrlFromController($this->get('router')));
    }

    public function redirectBackAction()
    {
        return $this->redirect($this->admin->generateUrl('list', ['filter' => $this->admin->getFilterParameters()]));
    }

    public function showEditAction($id = null)
    {
        $request = $this->getRequest();

        $id = $request->get($this->admin->getIdParameter());
        $object = $this->admin->getObject($id);

        if (!$object) {
            throw $this->createNotFoundException(sprintf('unable to find the object with id : %s', $id));
        }

        // restricted acces by postal code
        if ($this->user->getWatchModerationOnlyWithPostCodesRegexp()) {
            $regexp = $this->user->getWatchModerationOnlyWithPostCodesRegexp();
            $postalCode = $object->getAddress()->getPostalCode();
            if (! $postalCode || ! preg_match($regexp, $postalCode)) {
                return $this->redirect($this->admin->generateUrl('list'));
            }
        }

        $this->admin->checkAccess('edit', $object);
        $this->admin->setSubject($object);

        /** @var $form Form */
        $form = $this->admin->getForm();
        $form->setData($object);

        $view = $form->createView();

        // set the theme for the current Admin Form
        $this->get('twig')->getRuntime(\Symfony\Component\Form\FormRenderer::class)
             ->setTheme($view, $this->admin->getFormTheme());

        return $this->render('admin/edit/edit_element.html.twig', [
            'action' => 'edit',
            'form' => $view,
            'object' => $object,
            'elements' => $this->admin->getShow(),
        ], null);
    }

    public function editAction($id = null)
    {
        $request = $this->getRequest();

        $id = $request->get($this->admin->getIdParameter());
        $object = $this->admin->getObject($id);
        if (!$object) {
            throw $this->createNotFoundException(sprintf('unable to find the object with id : %s', $id));
        }
        $originalElement = $object->clone();

        $this->admin->checkAccess('edit', $object);
        $this->admin->setSubject($object);

        $form = $this->admin->getForm();
        $form->setData($object);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            $isFormValid = $form->isValid();

            // persist if the form was valid and if in preview mode the preview was approved
            if ($isFormValid) {
                try {
                    $this->handlesGoGoForm($object, $request);

                    $message = $request->get('custom_message') ? $request->get('custom_message') : '';
                    if ($request->get('submit_update_json')) {
                        $this->jsonGenerator->updateJsonRepresentation($object);
                    } elseif ($object->isPending() && ($request->get('submit_accept') || $request->get('submit_refuse'))) {
                        $this->elementActionService->resolve($object, $request->get('submit_accept'), ValidationType::Admin, $message);
                    } else if ($request->get('submit_send_to_osm')) {
                        $element = $this->admin->update($object);
                        return $this->redirectToRoute('gogo_send_to_osm', ['id' => $element->getId()]);
                    } else {
                        $sendMail = $request->get('send_mail');

                        if ($request->get('submit_delete')) {
                            $this->elementActionService->delete($object, $sendMail, $message);
                        } elseif ($request->get('submit_delete_permanently')) {
                            if ($object->getIsExternal()) {
                                $object->getSource()->addIdToIgnore($object->getOldId());
                            }
                            $this->dm->remove($object);
                            $this->dm->flush();
                            $this->addFlash(
                                'sonata_flash_success',
                                $this->trans(
                                    'flash_delete_success',
                                    ['%name%' => $this->escapeHtml($this->admin->toString($object))],
                                    'SonataAdminBundle'
                                )
                            );
                            return new RedirectResponse($this->admin->generateUrl('list'));
                        } elseif ($request->get('submit_restore')) {
                            $this->elementActionService->restore($object, $sendMail, $message);
                        } elseif ($request->get('submit_editAndKeepPending')) {
                            $keepStatus = true;
                            $this->elementActionService->edit($object, $originalElement, $sendMail, $message, $keepStatus);
                        } else {
                            $this->elementActionService->resolveReports($object, $message);
                            $this->elementActionService->edit($object, $originalElement, $sendMail, $message);
                        }
                    }

                    $object = $this->admin->update($object);

                    $this->addFlash(
                        'sonata_flash_success',
                        $this->trans(
                            'flash_edit_success',
                            ['%name%' => $this->escapeHtml($this->admin->toString($object))],
                            'SonataAdminBundle'
                        )
                    );

                    if ($request->get('submit_redirect')) {
                        return new RedirectResponse(
                            $this->admin->generateUrl('list')
                        );
                    }
                } catch (\Sonata\AdminBundle\Exception\ModelManagerException $e) {
                    $this->handleModelManagerException($e);
                    $isFormValid = false;
                } catch (\Sonata\AdminBundle\Exception\LockException $e) {
                    $this->addFlash('sonata_flash_error', $this->trans('flash_lock_error', [
                        '%name%' => $this->escapeHtml($this->admin->toString($object)),
                        '%link_start%' => '<a href="'.$this->admin->generateObjectUrl('edit', $object).'">',
                        '%link_end%' => '</a>',
                    ], 'SonataAdminBundle'));
                }
            }

            // show an error message if the form failed validation
            if (!$isFormValid) {
                $this->addFlash(
                    'sonata_flash_error',
                    $this->trans(
                        'flash_edit_error',
                        ['%name%' => $this->escapeHtml($this->admin->toString($object))],
                        'SonataAdminBundle'
                    )
                );
            }
        }

        return $this->redirectToRoute('admin_app_element_showEdit', ['id' => $id]);
    }

    private function handlesGoGoForm($element, $request)
    {
        $this->elementFormService->updateOptionsValues($element, $request);
        $newData = [];
        if ($request->get('data'))
            foreach($request->get('data') as $key => $value) {
                // array data is displayed with json_encode, so we decode it when saving
                $newData[slugify($key, false)] = json_decode($value) ?? $value;
            }
        $element->setCustomData($newData);
        $adr = $request->get('address');
        $address = new PostalAddress($adr['streetNumber'], $adr['streetAddress'], $adr['addressLocality'], $adr['postalCode'], $adr['addressCountry'], $adr['customFormatedAddress']);
        $element->setAddress($address);
        $geo = new Coordinates($request->get('latitude'), $request->get('longitude'));
        $element->setGeo($geo);
    }

    public function createAction()
    {
        $request = $this->getRequest();
        $this->admin->checkAccess('create');

        $newObject = $this->admin->getNewInstance();
        $this->admin->setSubject($newObject);

        $form = $this->admin->getForm();

        $pendingElement = $this->elementFormService->handlesBackFromCheckDuplicates();
        if ($pendingElement) {
            return $this->finishCreateProcess($pendingElement);
        }

        $this->container->get('session')->set('duplicatesFormAdmin', true);

        $form->setData($newObject);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            $isFormValid = $form->isValid();

            if ($isFormValid) {
                $element = $form->getData();
                $this->admin->setSubject($element);
                $this->admin->checkAccess('create', $element);
                $this->handlesGoGoForm($element, $request);
                $element = $this->admin->create($element);

                if ($this->elementFormService->checkDuplicates($element)) {
                    $this->container->get('session')->set('pendingElementDuplicate', $element->getId());
                    return $this->redirectToRoute('gogo_element_check_duplicate');
                } else {
                    return $this->finishCreateProcess($element);
                }
            }

            // show an error message if the form failed validation
            if (!$isFormValid) {
                $this->addFlash(
                    'sonata_flash_error',
                    $this->trans(
                        'flash_create_error',
                        ['%name%' => $this->escapeHtml($this->admin->toString($newObject))],
                        'SonataAdminBundle'
                    )
                );
            }
        }

        $formView = $form->createView();
        // set the theme for the current Admin Form
        $this->get('twig')->getRuntime(\Symfony\Component\Form\FormRenderer::class)
             ->setTheme($formView, $this->admin->getFormTheme());

        return $this->renderWithExtraParams('admin/core_custom/base_edit_and_create.html.twig', [
            'action' => 'create',
            'form' => $formView,
            'object' => $newObject,
            'objectId' => null,
        ], null);
    }

    function finishCreateProcess($element)
    {
        $this->elementActionService->add($element, "");
        $element->setPreventJsonUpdate(false); // ensute json get correctly updated
        $this->admin->update($element); // status has been modified by the elementActionService
        // send mail after element has been persisted (we need it's ID to generate the url)
        $this->elementActionService->afterAdd($element, true, "");

        $this->addFlash(
            'sonata_flash_success',
            $this->trans(
                'flash_create_success',
                ['%name%' => $this->escapeHtml($this->admin->toString($element))],
                'SonataAdminBundle'
            )
        );

        // redirect to edit mode
        return $this->redirectTo($element);
    }
}
