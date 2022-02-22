<?php
/**
 * Copyright © Echidna Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Echidna\NetsuiteOrderSyc\Block\System\Config\Form\Field;

use Magento\Config\Block\System\Config\Form\Field\FieldArray\AbstractFieldArray;
use Magento\Framework\Exception\LocalizedException;

class ShippingMethodDynamicRow extends AbstractFieldArray
{
    /**
     * @return void
     * @throws LocalizedException
     */
    protected function _construct()
    {
        $this->addColumn(
            'shipping_method',
            [
                'label' => __('Shipping Method'),
                'class' => 'validate-no-empty'
            ]
        );
        $this->addColumn(
            'shipping_method_internal_id',
            [
                'label' => __('Internal Id'),
                'class' => 'validate-no-empty'
            ]
        );
        $this->_addAfter = false;
        parent::_construct();
    }
}
