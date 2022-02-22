<?php

namespace Altayer\Support\Block\Adminhtml\Report\Edit;

/**
 * Class Form
 * @package Altayer\Support\Block\Adminhtml\Report\Edit
 */
class Form extends \Magento\Backend\Block\Widget\Form\Generic
{

    protected $_systemStore;

    /**
     * Form constructor.
     * @param \Magento\Backend\Block\Template\Context $context
     * @param \Magento\Framework\Registry $registry
     * @param \Magento\Framework\Data\FormFactory $formFactory
     * @param array $data
     */
    public function __construct(
        \Magento\Backend\Block\Template\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\Data\FormFactory $formFactory,
        array $data = array()
    )
    {
        parent::__construct($context, $registry, $formFactory, $data);
    }


    protected function _prepareForm()
    {
        $reportModel = $this->_coreRegistry->registry('report_model');
        $form = $this->_formFactory->create(
            [
                'data' => [
                    'id' => 'edit_form',
                    'action' => $this->getUrl('*/*/save'),
                    'method' => 'post',
                    'enctype' => 'multipart/form-data'
                ]
            ]
        );

        $form->setHtmlIdPrefix('report_');

        $fieldset = $form->addFieldset('base_fieldset',
            [
                'legend' => __('General Information')
            ]
        );

        $fieldset->addField(
            'report_name',
            'text',
            [
                'name' => 'report_name',
                'label' => __('Report Name'),
                'title' => __('Report Name'),
                'required' => true,
                'class' => 'required-entry'
            ]
        );

        $fieldset->addField(
            'report_sql',
            'textarea',
            [
                'name' => 'report_sql',
                'label' => __('Report SQL'),
                'title' => __('Report SQL'),
                'required' => true,
                'class' => 'required-entry',
                'after_element_html' => '<small>You can only execute select query</small>',
            ]
        );


        if (!is_null($reportModel)) {
            $fieldset->addField('report_id', 'hidden', array('name' => 'report_id'));
            $form->setValues($reportModel->getData());
        }

        $form->setUseContainer(true);
        $this->setForm($form);

        return parent::_prepareForm();
    }

    /**
     * Check permission for passed action
     *
     * @param string $resourceId
     * @return bool
     */
    protected function _isAllowedAction($resourceId)
    {
        return $this->_authorization->isAllowed($resourceId);
    }
}