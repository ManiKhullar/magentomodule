<?php
/**
 * @author Ryazuddin
 * @package Altayer_Support
 * @date 16/08/2020
 * */

namespace Altayer\Support\Model;

use \Magento\Framework\Model\AbstractModel;
use \Magento\Framework\DataObject\IdentityInterface;

class AtgReminder extends AbstractModel implements IdentityInterface
{
    const CACHE_TAG = 'atg_reminder';

    protected function _construct()
    {
        $this->_init('Altayer\Support\Model\ResourceModel\AtgReminder');
    }

    public function getIdentities()
    {
        return [self::CACHE_TAG . '_' . $this->getId()];
    }
}