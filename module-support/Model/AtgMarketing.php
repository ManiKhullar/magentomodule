<?php
/**
 * @author Amrendra Singh <amrendragr8@gmail.com>
 * @package Altayer_Support
 * */

namespace Altayer\Support\Model;

use \Magento\Framework\Model\AbstractModel;
use \Magento\Framework\DataObject\IdentityInterface;

class AtgMarketing extends AbstractModel implements IdentityInterface
{
    const CACHE_TAG = 'atg_marketing';

    protected function _construct()
    {
        $this->_init('Altayer\Support\Model\ResourceModel\AtgMarketing');
    }

    public function getIdentities()
    {
        return [self::CACHE_TAG . '_' . $this->getId()];
    }
}