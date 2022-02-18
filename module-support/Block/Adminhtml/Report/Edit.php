<?php

namespace Altayer\Support\Block\Adminhtml\Report;

use Altayer\Support\Block\Adminhtml\Report;

class Edit extends \Magento\Backend\Block\Widget\Form\Container
{
    /**
     * Core registry.
     *
     * @var \Magento\Framework\Registry
     */
    protected $_coreRegistry = null;

    /**
     * @param \Magento\Backend\Block\Widget\Context $context
     * @param \Magento\Framework\Registry           $registry
     * @param array                                 $data
     */
    public function __construct(
        \Magento\Backend\Block\Widget\Context $context,
        \Magento\Framework\Registry $registry,
        array $data = []
    )
    {
        $this->_coreRegistry = $registry;
        parent::__construct($context, $data);
    }


   
    protected function _construct()
    {
        $this->_objectId = 'id';
        $this->_blockGroup = 'altayer_support';
        $this->_controller = 'adminhtml_report';
        
        parent::_construct();

        if ($this->_isAllowedAction('Magento_Backend::content')) {
            $this->buttonList->update('save', 'label', __('Save'));
            $this->buttonList->update('delete', 'label', __('Delete'));
            $this->buttonList->add(
                'saveandcontinue',
                array(
                    'label' => __('Save and Continue Edit'),
                    'class' => 'save',
                    'data_attribute' => array(
                        'mage-init' => array('button' => array('event' => 'saveAndContinueEdit', 'target' => '#edit_form'))
                    )
                ),
                -100
            );
        } else {
            $this->buttonList->remove('save');
        }

        $this->buttonList->remove('reset');
    }

    /**
     * Check permission for passed action.
     *
     * @param string $resourceId
     *
     * @return bool
     */
    protected function _isAllowedAction($resourceId)
    {
        return $this->_authorization->isAllowed($resourceId);
    }

    /**
     * Get form action URL.
     *
     * @return string
     */
    public function getFormActionUrl()
    {
        if ($this->hasFormActionUrl()) {
            return $this->getData('form_action_url');
        }

        return $this->getUrl('*/*/save');
    }
}