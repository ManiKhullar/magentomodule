<?php

/**
 * @author   Mani <kmanidev6@gmail.com>
 * @package    Altayer_Support
 */

namespace Altayer\Support\Model;

use Magento\Framework\Model\AbstractModel;
use Magento\Framework\DataObject\IdentityInterface;

/**
 * Class AtgVpnColorMapping
 * @package Altayer\Support\Model
 */
class AtgVpnColorMapping extends AbstractModel implements IdentityInterface
{
    const CACHE_TAG = 'atg_vpn_color_mapping';

    protected function _construct()
    {
        $this->_init('Altayer\Support\Model\ResourceModel\AtgVpnColorMapping');
    }

    public function getIdentities()
    {
        return [self::CACHE_TAG . '_' . $this->getId()];
    }
}
