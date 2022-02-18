<?php
/**
 * Copyright © Echidna Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Echidna\NetsuiteOrderSyc\Block\System\Config\Form\Field;

use Magento\Config\Block\System\Config\Form\Field\FieldArray\AbstractFieldArray;

class LocationDynamicRow extends AbstractFieldArray
{
    /**
     * @return void
     */
    protected function _construct()
    {
        $this->addColumn(
            'netsuite_zipcode',
            [
                'label' => __('Zip Code'),
                'class' => 'validate-no-empty'
            ]
        );
        $this->addColumn(
            'netsuite_zipcode_internal_id',
            [
                'label' => __('Internal Id'),
                'class' => 'validate-no-empty'
            ]
        );
        $this->_addAfter = false;
        parent::_construct();
    }
}
