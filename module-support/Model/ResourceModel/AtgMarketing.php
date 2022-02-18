<?php
/**
 * @author Amrendra Singh <amrendragr8@gmail.com>
 * @package Altayer_Support
 * */

namespace Altayer\Support\Model\ResourceModel;


class AtgMarketing extends \Magento\Framework\Model\ResourceModel\Db\AbstractDb
{
    protected function _construct()
    {
        $this->_init('atg_marketing', 'quote_id');
    }

    /** Before save if you want to do any validation
     * @param \Magento\Framework\Model\AbstractModel $object
     */
    protected function _beforeSave(\Magento\Framework\Model\AbstractModel $object)
    {
        return parent::_beforeSave($object);

    }
}