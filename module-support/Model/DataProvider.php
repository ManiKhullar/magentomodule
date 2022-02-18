<?php

/**
 * @author     Amarendra <ryazuddin@altayer.com>
 * @package    Altayer_Support
 */

namespace Altayer\Support\Model;

use Magento\Framework\Model\AbstractModel;
use Magento\Framework\DataObject\IdentityInterface;

/**
 * Class AtgVpnData
 * @package Altayer\Support\Model
 */
class DataProvider extends AbstractModel implements IdentityInterface
{
    const CACHE_TAG = 'atg_vpn_data';

    protected function _construct()
    {
        $this->_init('Altayer\Support\Model\ResourceModel\DataProvider');
    }

    public function getIdentities()
    {
        return [self::CACHE_TAG . '_' . $this->getId()];
    }
}
