<?php

/**
 * @author Ryazuddin
 * @package Altayer_Support
 * @date 16/08/2020
 * */

namespace Altayer\Support\Model\ResourceModel\AtgVpnData;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{
    protected function _construct()
    {
        $this->_init('Altayer\Support\Model\AtgVpnData', 'Altayer\Support\Model\ResourceModel\AtgVpnData');
    }
}