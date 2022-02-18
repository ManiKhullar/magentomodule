<?php
/**
 * @author Ryazuddin
 * @package Altayer_Support
 * @date 16/08/2020
 * */

namespace Altayer\Support\Model\ResourceModel;


class AtgReminder extends \Magento\Framework\Model\ResourceModel\Db\AbstractDb
{
    protected function _construct()
    {
        $this->_init('atg_reminder', 'quote_id');
    }

    /** Before save if you want to do any validation
     * @param \Magento\Framework\Model\AbstractModel $object
     */
    protected function _beforeSave(\Magento\Framework\Model\AbstractModel $object)
    {
        return parent::_beforeSave($object);

    }
}