<?php
namespace Altayer\Support\Block\Adminhtml\Form\Field;

use Magento\Config\Block\System\Config\Form\Field\FieldArray\AbstractFieldArray;

/**
 * OrderRanges.php
 * @package   Altayer\Support\Model\Config\Source
 * @author   Mani <kmanidev6@gmail.com>
 */


class OrderRanges extends AbstractFieldArray
{
    /**
     * Prepare to render
     *
     * @return void
     */
    protected function _prepareToRender()
    {
        $this->addColumn('from_order_id', ['label' => __('From Order Id')]);
        $this->addColumn('to_order_id', ['label' => __('To Order Id')]);
        $this->_addAfter = false;
        $this->_addButtonLabel = __('Add Order Ids');
    }

}
