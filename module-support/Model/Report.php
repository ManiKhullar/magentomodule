<?php

namespace Altayer\Support\Model;

use \Magento\Framework\Model\AbstractModel;
use \Magento\Framework\DataObject\IdentityInterface;

class Report extends AbstractModel implements ReportInterface, IdentityInterface
{
    const CACHE_TAG = 'atg_report';

    protected function _construct()
    {
        $this->_init('Altayer\Support\Model\ResourceModel\Report');
    }

    public function getIdentities()
    {
        return [self::CACHE_TAG . '_' . $this->getId()];
    }
}
