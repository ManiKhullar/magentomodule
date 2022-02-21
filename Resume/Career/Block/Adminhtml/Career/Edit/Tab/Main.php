<?php
namespace Resume\Career\Block\Adminhtml\Career\Edit\Tab;

/**
 * News page edit form main tab
 */
class Main extends \Magento\Backend\Block\Widget\Form\Generic implements \Magento\Backend\Block\Widget\Tab\TabInterface
{
    /**
     * @var \Magento\Store\Model\System\Store
     */
    protected $_systemStore;

    /**
     * @param \Magento\Backend\Block\Template\Context $context
     * @param \Magento\Framework\Registry $registry
     * @param \Magento\Framework\Data\FormFactory $formFactory
     * @param \Magento\Store\Model\System\Store $systemStore
     * @param array $data
     */
    public function __construct(
        \Magento\Backend\Block\Template\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\Data\FormFactory $formFactory,
        \Magento\Store\Model\System\Store $systemStore,
        array $data = []
    ) {
        $this->_systemStore = $systemStore;
        parent::__construct($context, $registry, $formFactory, $data);
    }

    /**
     * Prepare form
     *
     * @return $this
     */
    protected function _prepareForm()
    {
        /* @var $model \Magento\Cms\Model\Page */
        $model = $this->_coreRegistry->registry('career');

        /*
         * Checking if user have permissions to save information
         */
        if ($this->_isAllowedAction('Resume_Career::save')) {
            $isElementDisabled = false;
        } else {
            $isElementDisabled = true;
        }

        /** @var \Magento\Framework\Data\Form $form */
        $form = $this->_formFactory->create();

        $form->setHtmlIdPrefix('career_main_');

        $fieldset = $form->addFieldset('base_fieldset', ['legend' => __('Career Information')]);

        if ($model->getId()) {
            $fieldset->addField('ingredient_id', 'hidden', ['name' => 'ingredient_id']);
        }

        $fieldset->addField(
            'name',
            'text',
            [
                'name' => 'name',
                'label' => __('Applicant Name'),
                'title' => __('Applicant Title'),
                'required' => true,
                'disabled' => $isElementDisabled
            ]
        );
        
        $fieldset->addField(
            'email',
            'text',
            [
                'name' => 'email',
                'label' => __('Email'),
                'title' => __('Email'),
                'required' => true,
                'disabled' => $isElementDisabled
            ]
        );
        
        $fieldset->addField(
            'telephone',
            'text',
            [
                'name' => 'telephone',
                'label' => __('Contact No.'),
                'title' => __('Contact No.'),
                'required' => true,
                'disabled' => $isElementDisabled
            ]
        );
        
        $fieldset->addField(
            'store_location',
            'text',
            [
                'name' => 'store_location',
                'label' => __('Store Location'),
                'title' => __('Store Location'),
                'required' => true,
                'disabled' => $isElementDisabled
            ]
        );
        
        $fieldset->addField(
            'current_location',
            'text',
            [
                'name' => 'current_location',
                'label' => __('Current Location'),
                'title' => __('Current Location'),
                'required' => true,
                'disabled' => $isElementDisabled
            ]
        );
        
        $fieldset->addField(
            'current_ctc',
            'text',
            [
                'name' => 'current_ctc',
                'label' => __('Current CTC'),
                'title' => __('Current CTC'),
                'required' => false,
                'disabled' => $isElementDisabled
            ]
        );
        
        $fieldset->addField(
            'linkedin_profile',
            'text',
            [
                'name' => 'linkedin_profile',
                'label' => __('Linkedin Profile'),
                'title' => __('Linkedin Profile'),
                'required' => false,
                'disabled' => $isElementDisabled
            ]
        );

        $fieldset->addField(
                'is_active',
                'select',
                [
                    'name' => 'is_active',
                    'label' => __('Status'),
                    'id' => 'is_active',
                    'title' => __('Account Status'),
                    'class' => 'input-select',
                    'options' => ['1' => __('Active'), '0' => __('Inactive')]
                ]
            );
        
        
        
        $this->_eventManager->dispatch('adminhtml_career_edit_tab_main_prepare_form', ['form' => $form]);

        $form->setValues($model->getData());
        $this->setForm($form);

        return parent::_prepareForm();
    }

    /**
     * Prepare label for tab
     *
     * @return string
     */
    public function getTabLabel()
    {
        return __('Career Information');
    }

    /**
     * Prepare title for tab
     *
     * @return string
     */
    public function getTabTitle()
    {
        return __('Career Information');
    }

    /**
     * {@inheritdoc}
     */
    public function canShowTab()
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function isHidden()
    {
        return false;
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
