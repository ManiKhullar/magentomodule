<?php
/**
 * Copyright © Echidna Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Echidna\NetsuiteOrderSyc\Block\System\Config\Form\Field;

use Magento\Config\Block\System\Config\Form\Field\FieldArray\AbstractFieldArray;

class FixLocationDynamicRow extends AbstractFieldArray
{
    /**
     * @return void
     */
    protected function _prepareToRender()
    {
        $this->addColumn(
            'shipping_method',
            [
                'label' => __('Shipping Method'),
                'class' => 'required-entry'
            ]);
        $this->addColumn(
            'location_internal_id',
            [
                'label' => __('Location Internal Id'),
                'class' => 'required-entry'
            ]);

        $this->_addAfter = false;
        $this->_addButtonLabel = __('Add');
    }
}
