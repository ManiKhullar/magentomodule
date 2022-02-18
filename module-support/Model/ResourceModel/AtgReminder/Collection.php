<?php

/**
 * @author Ryazuddin
 * @package Altayer_Support
 * @date 16/08/2020
 * */

namespace Altayer\Support\Model\ResourceModel\AtgReminder;

class Collection extends \Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection
{
    protected function _construct()
    {
        $this->_init('Altayer\Support\Model\AtgReminder', 'Altayer\Support\Model\ResourceModel\AtgReminder');
    }
}