<?php
namespace Altayer\Support\Block\Adminhtml;

abstract class Report extends \Magento\Backend\Block\Widget\Grid\Container
{
    /**
     * @return void
     */
    protected function _construct()
    {
        $this->_blockGroup = 'atg_support';
        $this->_controller = 'adminhtml_report';
        $this->_headerText = __('Report');
        $this->_addButtonLabel = __('Add New Report');
        parent::_construct();
    }
}
