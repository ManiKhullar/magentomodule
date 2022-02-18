<?php
/**
 * Copyright © 2016 Echidna. All rights reserved.
 * User: shikha
 */

namespace Echidna\NetsuiteSalesOrder\Block\System\Config\Form\Field;

use Magento\Config\Block\System\Config\Form\Field\FieldArray\AbstractFieldArray;
use Magento\Framework\DataObject;

/**
 * Class Price List Backend system config array field renderer
 */
class CustomDisplaySourceList extends AbstractFieldArray
{

    /**
     * Initialise columns for Auto add Product SKU
     *
     * @return void
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    protected function _construct()
    {
        $this->addColumn(
            'netsuite_location',
            [
                'label' => __('Netsuite Location'),
                'class' => 'validate-no-empty'
            ]
        );
        $this->addColumn(
            'source_code',
            [
                'label' => __('Source Code'),
                'class' => 'validate-no-empty'
            ]
        );
        $this->_addAfter = false;
        parent::_construct();
    }


}
