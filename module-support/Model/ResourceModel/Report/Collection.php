<?php

namespace Altayer\Support\Model\ResourceModel\Report;

class Collection extends \Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection
{
    protected function _construct()
    {
        $this->_init('Altayer\Support\Model\Report', 'Altayer\Support\Model\ResourceModel\Report');
    }
}
