<?php
/**
 * @Author: Sebastian Castro
 * @Date:   2017-03-28 15:29:03
 * @Last Modified by:   Sebastian Castro
 * @Last Modified time: 2018-06-05 17:39:59
 */

namespace App\Admin\Element;

use App\Document\ElementStatus;
use App\Helper\GoGoHelper;
use Sonata\AdminBundle\Datagrid\ListMapper;
use Sonata\AdminBundle\Route\RouteCollection;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Security;

class ElementAdminList extends ElementAdminFilters
{
    public $selectedColumns;
    public $availableColumns;
    public $user;

    public function __construct($code, $class, $baseControllerName, Security $security)
    {
        parent::__construct($code, $class, $baseControllerName);
        $this->user = $security->getUser();
    }
    
    public function getTemplate($name)
    {
        switch ($name) {
            case 'list': return 'admin/list/list_element.html.twig';
                break;
            default: return parent::getTemplate($name);
                break;
        }
    }

    public function createQuery($context = 'list')
    {
        $query = parent::createQuery($context);
        // not display the modified version
        $query->field('status')->notEqual(ElementStatus::ModifiedPendingVersion);
        $query->field('status')->exists(true);
        // restricted acces by postal code
        if ($this->user->getWatchModerationOnlyWithPostCodesRegexp()) {
            $regexp = $this->user->getWatchModerationOnlyWithPostCodesRegexp();
            $query->field('address.postalCode')->equals(new \MongoRegex($regexp));
        }
        return $query;
    }

    protected function configureRoutes(RouteCollection $collection)
    {
        $collection->add('redirectShow', $this->getRouterIdParameter().'/redirectShow');
        $collection->add('redirectEdit', $this->getRouterIdParameter().'/redirectEdit');
        $collection->add('showEdit', $this->getRouterIdParameter().'/show-edit');
    }

    protected function configureListFields(ListMapper $listMapper)
    {
        $dm = GoGoHelper::getDmFromAdmin($this);
        $this->availableColumns = array_merge(['categories', 'moderation'], $dm->get('Element')->findDataCustomProperties());
        $this->selectedColumns = $this->session->get('element-list-fields', ['categories', 'moderation']) ?? [];
        $listMapper
         ->add('name', null, ['editable' => false, 'template' => 'admin/partials/list_name.html.twig'])
         ->add('status', ChoiceType::class, [
               'editable' => true,
               'template' => 'admin/partials/list_choice_status.html.twig',
               ])
         ->add('updatedAt', 'date', ['template' => 'admin/partials/list_updated_at.html.twig'])
         ->add('sourceKey');

        foreach($this->selectedColumns as $field) {
            if ($field == 'categories') {
                $listMapper->add('optionsString', null, ['header_style' => 'width: 250px']);
            } elseif ($field == 'moderation') {
                $listMapper->add('moderationState', ChoiceType::class, [
                    'template' => 'admin/partials/list_choice_moderation.html.twig',
                ]);
            } else {
                $listMapper->add($field, null, [
                    'template' => 'admin/partials/list_custom_data.html.twig',
                    'field' => $field
                ]);
            }
        }

        $listMapper->add('_action', 'actions', [
             'actions' => [
                 'show-edit' => ['template' => 'admin/partials/list__action_show_edit.html.twig'],
                 //'edit' => array('template' => 'admin/partials/list__action_edit.html.twig'),
                 //'delete' => array('template' => 'admin/partials/list__action_delete.html.twig'),
                 'redirect-show' => ['template' => 'admin/partials/list__action_redirect_show.html.twig'],
                 'redirect-edit' => ['template' => 'admin/partials/list__action_redirect_edit.html.twig'],
             ],
         ]);
    }

    public function configureBatchActions($actions)
    {
        $actions = [];
        $actions['validation'] = $this->createBatchConfig('validation');
        $actions['refusal'] = $this->createBatchConfig('refusal');
        $actions['softDelete'] = $this->createBatchConfig('softDelete');
        $actions['restore'] = $this->createBatchConfig('restore');
        $actions['resolveReports'] = $this->createBatchConfig('resolveReports');

        $actions['sendMail'] = [
            'label' => $this->trans('elements.action.batch.sendMail'),
            'ask_confirmation' => false,
            'modal' => [
                ['type' => 'text',      'label' => $this->trans('elements.action.batch.params.from'),  'id' => 'from'],
                ['type' => 'text',      'label' => $this->trans('elements.action.batch.params.mail_subject'),  'id' => 'mail-subject'],
                ['type' => 'textarea',  'label' => $this->trans('elements.action.batch.params.mail_content'), 'id' => 'mail-content'],
                ['type' => 'checkbox',  'label' => $this->trans('elements.action.batch.params.send_to_element'),  'id' => 'send-to-element', 'checked' => 'true'],
                ['type' => 'checkbox',  'label' => $this->trans('elements.action.batch.params.send_to_last_contributor'),  'id' => 'send-to-last-contributor', 'checked' => 'false'],
            ],
        ];
        $actions['editOptions'] = [
            'label' => $this->trans('elements.action.batch.editOptions'),
            'ask_confirmation' => false,
            'modal' => [
                ['type' => 'choice',  'choices' => $this->getOptionsChoices(), 'id' => 'optionsToRemove', 'label' => $this->trans('elements.action.batch.params.optionsToRemove')],
                ['type' => 'choice',  'choices' => $this->getOptionsChoices(), 'id' => 'optionsToAdd', 'label' => $this->trans('elements.action.batch.params.optionsToAdd')],
            ],
        ];

        $actions['editData'] = [
            'label' => $this->trans('elements.action.batch.editData'),
            'ask_confirmation' => false,
            'modal' => [
                ['type' => 'text', 'id' => 'fieldName',  'label' => $this->trans('elements.action.batch.params.customField')],
                ['type' => 'text', 'id' => 'fieldValue', 'label' => $this->trans('elements.action.batch.params.value')],
            ],
        ];

        $actions['delete'] = ['label' => $this->trans('elements.action.batch.delete')];

        return $actions;
    }

    protected function createBatchConfig($id)
    {
        return [
            'label' => $this->trans('elements.action.batch.'.$id),
            'ask_confirmation' => false,
            'modal' => [
                ['type' => 'text',  'label' => $this->trans('element.form.admin_section.custom_message', [], 'messages'), 'id' => 'comment-'.$id],
                ['type' => 'checkbox',  'checked' => true, 'label' => $this->trans('elements.action.batch.params.dont_send_mail'),  'id' => 'dont-send-mail-'.$id],
            ],
        ];
    }
}
