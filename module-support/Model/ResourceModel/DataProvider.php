<?php
/**
 * @author   Mani <kmanidev6@gmail.com>
 * @package Altayer_Support
 * @date 16/08/2020
 * */

namespace Altayer\Support\Model\ResourceModel;
use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class DataProvider extends AbstractDb
{
    protected function _construct()
    {
        $this->_init('atg_vpn_data', 'entity_id');
    }

    /** Before save if you want to do any validation
     * @param \Magento\Framework\Model\AbstractModel $object
     */
    protected function _beforeSave(\Magento\Framework\Model\AbstractModel $object)
    {
        return parent::_beforeSave($object);

    }
}
