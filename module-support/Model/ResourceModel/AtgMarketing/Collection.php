<?php

namespace Altayer\Support\Model\ResourceModel\AtgMarketing;

class Collection extends \Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection
{
    protected function _construct()
    {
        $this->_init('Altayer\Support\Model\AtgMarketing', 'Altayer\Support\Model\ResourceModel\AtgMarketing');
    }
}