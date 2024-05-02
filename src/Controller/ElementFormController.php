<?php

/**
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @copyright Copyright (c) 2016 Sebastian Castro - 90scastro@gmail.com
 * @license    MIT License
 * @Last Modified time: 2018-07-08 16:44:57
 */

namespace App\Controller;

use App\Document\Element;
use App\Document\ElementStatus;
use App\Form\ElementType;
use App\Services\ConfigurationService;
use App\Services\ElementActionService;
use App\Services\ElementDuplicatesService;
use App\Services\ElementFormService;
use App\Services\ElementSynchronizationService;
use App\Services\TaxonomyService;
use Doctrine\ODM\MongoDB\DocumentManager;
use FOS\UserBundle\Model\UserManagerInterface;
use FOS\UserBundle\Security\LoginManagerInterface;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Contracts\Translation\TranslatorInterface;


class ElementFormController extends GoGoController
{
    private $editMode;
    private $isLoggedIn;
    private $isAllowedDirectModeration;
    private $isUserOwnerOfValidElement;

    public function __construct(ElementSynchronizationService $synchService,
                                SessionInterface $session, DocumentManager $dm,
                                ConfigurationService $configService,
                                ElementFormService $elementFormService,
                                UserManagerInterface $userManager,
                                LoginManagerInterface $loginManager,
                                TranslatorInterface $t,
                                TaxonomyService $taxonomyService)
    {
        $this->synchService = $synchService;
        $this->session = $session;
        $this->dm = $dm;
        $this->configService = $configService;
        $this->elementFormService = $elementFormService;
        $this->userManager = $userManager;
        $this->loginManager = $loginManager;
        $this->t = $t;
        $this->taxonomyService = $taxonomyService;
        $this->config = $configService->getConfig();
    }

    public function addAction(Request $request)
    {
        $this->editMode = false;
        return $this->renderForm(new Element(), $request);
    }

    public function editAction($id, Request $request)
    {
        $this->editMode = true;
        $element = $this->dm->get('Element')->find($id);

        if (!$element) {
            $this->addFlash('error', $this->t->trans('element.form.controller.error_dont_exist'));

            return $this->redirectToRoute('gogo_directory');
        } elseif ($element->getStatus() > ElementStatus::PendingAdd && !$element->isExternalReadOnly()
            || $this->configService->isUserAllowed('directModeration')
            || ($element->isPending() && $element->getRandomHash() == $request->get('hash'))) {
            return $this->renderForm($element, $request);
        } else {
            $this->addFlash('error', $this->t->trans('element.form.controller.error_unauthorized'));

            return $this->redirectToRoute('gogo_directory');
        }
    }

    // render for both Add and Edit actions
    private function renderForm($element, $request)
    {
        if (null === $element) {
            throw new NotFoundHttpException($this->t->trans('element.form.controller.error_dont_exist'));
        }

        if ($request->get('logout')) {
            $this->session->remove('emailToCreateAccount');
        }

        $isEditingWithHash = $element->getRandomHash() && $element->getRandomHash() == $request->get('hash');
        $this->isLoggedIn = $this->get('security.authorization_checker')->isGranted('IS_AUTHENTICATED_REMEMBERED');

        // Create user uppon element form submission (first we ask email, then password inside the element form)
        if ($request->request->get('input-password') && !$this->isLoggedIn) {
            // Create user and set details
            $user = $this->userManager->createUser();
            $user->setUserName($this->session->get('emailToCreateAccount'));
            $user->setEmail($this->session->get('emailToCreateAccount'));
            $user->setPlainPassword($request->request->get('input-password'));
            $user->setEnabled(true);
            $this->userManager->updateUser($user, true);
            $this->dm->persist($user);
            // Authenticate User
            $this->loginManager->loginUser($this->getParameter('fos_user.firewall_name'),$user, null);
            $this->isLoggedIn = true;
            // Add flash message
            $text = $this->t->trans('element.form.controller.success', ['%url%' => $this->generateUrl('gogo_user_profile')] );
            $this->session->getFlashBag()->add('success', $text);
        }

        // is user not allowed, we show the contributor-login page
        $featureName = $this->editMode ? 'edit' : 'add';
        if (!$this->configService->isUserAllowed($featureName, $request) && !$isEditingWithHash) {
            // creating simple form to let user enter an email address
            $loginform = $this->get('form.factory')->createNamedBuilder('user', FormType::class)
                ->add('email', EmailType::class, ['required' => true])
                ->getForm();
            $loginEmail = $request->request->get('user')['email'] ?? '';
            $emailAlreadyUsed = false;
            if ($loginEmail) {
                $othersUsers = $this->dm->get('User')->findByEmail($loginEmail);
                $emailAlreadyUsed = count($othersUsers) > 0;
            }
            $loginform->handleRequest($request);
            if ($loginform->isSubmitted() && $loginform->isValid() && !$emailAlreadyUsed) {
                $this->session->set('emailToCreateAccount', $loginEmail);
            } else {
                return $this->render('element-form/contributor-login.html.twig', [
                    'loginForm' => $loginform->createView(),
                    'emailAlreadyUsed' => $emailAlreadyUsed,
                    'config' => $this->config,
                    'featureConfig' => $this->configService->getFeatureConfig($featureName), ]);
            }
        }

        $userEmail = $this->isLoggedIn ? $this->getUser()->getEmail() : $this->session->get('emailToCreateAccount') ?? '';

        // We need to detect if the owner contribution has been validated. Because after that, the owner have direct moderation on the element
        // To check that, we check is element is Valid or element is pending but from a contribution not made by the owner
        $this->isUserOwnerOfValidElement =
            $this->isLoggedIn && $this->editMode
            && ($element->isValid() || $element->isPending() && $element->getCurrContribution() && $element->getCurrContribution()->getUserEmail() != $userEmail)
            && $element->getUserOwnerEmail() && $element->getUserOwnerEmail() == $userEmail;

        $ownerAllowedDirectModeration = $this->config->getOwnerAllowedDirectModeration();
        $this->isAllowedDirectModeration =
            $this->configService->isUserAllowed('directModeration')
            || !$this->editMode && $this->isLoggedIn && $this->getUser()->hasRole('ROLE_DIRECTMODERATION_ADD')
            || $this->editMode && $this->isLoggedIn && $this->getUser()->hasRole('ROLE_DIRECTMODERATION_EDIT_OWN_CONTRIB') && $element->hasValidContributionMadeBy($userEmail)
            || $this->isUserOwnerOfValidElement && $ownerAllowedDirectModeration
            || $isEditingWithHash && !$element->isPending() && $ownerAllowedDirectModeration;

        $originalElement = $element->clone(); // will be use to compute changeset
        $pendingElement = $this->elementFormService->handlesBackFromCheckDuplicates();
        if ($pendingElement) {
            return $this->finishCreateEditProcess($pendingElement, $originalElement, $request);
        }

        // create the element form
        $elementForm = $this->get('form.factory')->create(ElementType::class, $element);
        $elementForm->handleRequest($request);

        // Fix bug with openhours, which become empty when openhours field is missing in form
        $formFields = $this->config->getElementFormFields();
        $openHoursInForm = array_key_exists('openhours', array_column($formFields, 'type', 'type'));
        if (!$openHoursInForm && $originalElement->getOpenHours()) {
            $element->setOpenHours($originalElement->getOpenHours());
        }

        // If form submitted with valid values
        if ($elementForm->isSubmitted() && $elementForm->isValid()) {
            $element = $this->elementFormService->handleFormSubmission($element, $request, $userEmail);
            
            if ($this->editMode && !$element->isFullyEditable()) {
                return $this->renderFormView($element, $elementForm);
            }

            if (!$this->editMode && $this->elementFormService->checkDuplicates($element)) {
                // Save new element so we can find it again during duplicate process
                $this->dm->persist($element);
                $this->dm->flush();
                $this->session->set('pendingElementDuplicate', $element->getId());
                return $this->redirectToRoute('gogo_element_check_duplicate');
            } else {
                return $this->finishCreateEditProcess($element, $originalElement, $request);
            }
        } // ends  handling submitted form

        return $this->renderFormView($element, $elementForm);
    }

    private function renderFormView($element, $elementForm) {
        // If pending modif, we update the modifiedElement (user editing it's own pending contrib)
        $elementToFillTheForm = $element->isPendingModification() ? $element->getModifiedElement() : $element;

        return $this->render('element-form/element-form.html.twig', [
            'editMode' => $this->editMode,
            'form' => $elementForm->createView(),
            'taxonomy' => $this->taxonomyService->getTaxonomyJson(),
            'element' => $elementToFillTheForm,
            'isAllowedDirectModeration' => $this->isAllowedDirectModeration,
            'config' => $this->config,
            'imagesMaxFilesize' => $this->detectMaxUploadFileSize('images'),
            'filesMaxFilesize' => $this->detectMaxUploadFileSize('files'),
            'isOwner' => $this->isUserOwnerOfValidElement
        ]);
    }

    private function finishCreateEditProcess($element, $originalElement, $request)
    {
        $element = $this->elementFormService->save($element, $originalElement, $request, $this->isAllowedDirectModeration);
        $this->dm->persist($element);
        $this->dm->flush();
        // Send add email (now the element is persisted)
        if (!$originalElement->getId()) $this->elementFormService->afterAdd($element, $request);

        // Create flash message
        $elementShowOnMapUrl = $element->getShowUrlFromController($this->get('router'));
        $recopyInfo = $request->request->get('recopyInfo');
        $submitOption = $request->request->get('submit-option');
        if ($this->editMode) {
            $noticeText = $this->t->trans('element.form.controller.thankyou.edited');
        } else {
            $noticeText = $this->t->trans('element.form.controller.thankyou.added', ['%name%' => ucwords($this->config->getElementDisplayNameDefinite())]);
        }
        if ($element->isPending()) {
            $noticeText .= "<br/>".$this->t->trans('element.form.controller.pending');
            if ($this->configService->getFeatureConfig('vote')->getActive())
                $noticeText .= <<<HTML
                    , <a class='validation-process' onclick="$('#popup-collaborative-explanation').openModal()">
                        {$this->t->trans('commons.click_here')}
                    </a> {$this->t->trans('element.form.controller.pending_collaborative')}
                HTML;
        }
        if ($this->isLoggedIn) {
            $noticeText .= '<br/>'.$this->t->trans('element.form.controller.user_contributions', ['%url%' => $this->generateUrl('gogo_user_contributions')]);
        }
        if ($submitOption == 'stayonform' ) {
            $noticeText .= "<br/><a href='$elementShowOnMapUrl'>{$this->t->trans('element.form.controller.see_on_map')}</a>";
        }
        $this->session->getFlashBag()->add('success', $noticeText);

        // Render a new form a redirect to map
        if ($submitOption == 'stayonform' || $recopyInfo) {
            if (!$recopyInfo) $element = new Element();
            $elementForm = $this->get('form.factory')->create(ElementType::class, $element);
            $this->editMode = false;
            return $this->renderFormView($element, $elementForm);
        } else {
            return $this->redirect($elementShowOnMapUrl);
        }
    }

    // when submitting new element, check it's not yet existing
    public function checkDuplicatesAction(Request $request,
                                          ElementDuplicatesService $duplicateService,
                                          ElementActionService $actionService)
    {
        // a form with just a submit button
        $checkDuplicatesForm = $this->get('form.factory')->createNamedBuilder('duplicates', FormType::class)
                                                         ->getForm();

        // If Form is Submitted
        if ($request->getMethod() == 'POST') {
            if ($request->get('notduplicate')) {
                // Go back to add action to finish creation process
                if ($this->session->get('redirectToIfNoDuplicate'))
                    return $this->redirect($this->session->get('redirectToIfNoDuplicate'));
                $route = $this->session->get('duplicatesFormAdmin') ? 'admin_app_element_create' : 'gogo_element_add';
                return $this->redirectToRoute($route);
            } else {
                // It's a duplicate
                $pendingElement = $this->dm->get('Element')->find($this->session->get('pendingElementDuplicate'));
                $isOsmDuplicate = $request->get('osm');
                if ($isOsmDuplicate) {
                    $osmId = array_keys($request->get('osm'))[0];
                    $originalElement = $pendingElement->clone(); // will be use to compute changeset
                    $this->synchService->linkElementToOsmDuplicate($pendingElement, $osmId);
                    $mergedElement = $pendingElement;
                } else {
                    $gogoId = array_keys($request->get('gogo'))[0];

                    $mergedElement = $this->dm->get('Element')->find("$gogoId");
                    $originalElement = $mergedElement->clone(); // will be use to compute changeset
                    $duplicateService->automaticMerge($mergedElement, [$pendingElement], false);
                    // pending element can now be deleted
                    // Reset files and images first so they are not deleted (cause merged element is using them)
                    $pendingElement->resetFiles();
                    $pendingElement->resetImages();
                    if ($pendingElement) $this->dm->remove($pendingElement);

                }
                // Save changes made during the merge
                $msg = $this->t->trans("duplicates.merged_with_new_element");
                $this->dm->persist($mergedElement); // do persist before actionService edit
                $actionService->edit($mergedElement, $originalElement, false, $msg);
                $this->dm->flush();

                // Redirect to edit
                $flash = $this->t->trans('duplicates.merged_with_duplicate');
                $url = $mergedElement->getShowUrlFromController($this->get('router'));
                $flash .= "<br/><a href='$url'>{$this->t->trans('element.form.controller.see_on_map')}</a>";

                $this->session->getFlashBag()->add('success', $flash);
                $route = $this->session->get('duplicatesFormAdmin') ? 'admin_app_element_showEdit' : 'gogo_element_edit';

                $this->session->remove('pendingElementDuplicate');
                $this->session->remove('duplicatesElements');
                $this->session->remove('duplicatesFormAdmin');

                return $this->redirectToRoute($route, ['id' => $mergedElement->getId()]);
            }

        }
        elseif ($this->session->has('duplicatesElements') && count($this->session->get('duplicatesElements')) > 0) {
            // Display the check duplicates form
            $duplicates = $this->session->get('duplicatesElements');
            $config = $this->dm->get('Configuration')->findConfiguration();
            return $this->render('element-form/check-for-duplicates.html.twig', [
                'duplicateForm' => $checkDuplicatesForm->createView(),
                'duplicatesElements' => $duplicates,
                'config' => $config ]);
        }
        else {
            return $this->redirectToRoute('gogo_element_add');
        }
    }

    /**
     * Detects max size of file cab be uploaded to server.
     *
     * Based on php.ini parameters "upload_max_filesize", "post_max_size" &
     * "memory_limit". Valid for single file upload form. May be used
     * as MAX_FILE_SIZE hidden input or to inform user about max allowed file size.
     *
     * @return int Max file size in bytes
     */
    private function detectMaxUploadFileSize($key = null)
    {
        /**
         * Converts shorthands like "2M" or "512K" to bytes.
         *
         * @param $size
         *
         * @return mixed
         */
        $normalize = function ($size) {
            if (preg_match('/^([\d\.]+)([KMG])$/i', $size, $match)) {
                $pos = array_search($match[2], ['K', 'M', 'G']);
                if (false !== $pos) {
                    $size = $match[1] * pow(1024, $pos + 1);
                }
            }

            return $size;
        };

        $max_upload = $normalize(ini_get('upload_max_filesize'));
        $max_post = $normalize(ini_get('post_max_size'));
        $memory_limit = $normalize(ini_get('memory_limit') != '-1' ? ini_get('memory_limit') : '999G' );
        $maxFileSize = min($max_upload, $max_post, $memory_limit);

        if ($key) {
            $appMaxsize = $this->getParameter($key.'_max_filesize');
            $maxFileSize = min($maxFileSize, $normalize($appMaxsize));
        }

        return $maxFileSize;
    }
}
