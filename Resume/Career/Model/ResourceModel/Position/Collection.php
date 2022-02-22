<?php
/**
 * Position Resource Collection
 */
namespace Resume\Career\Model\ResourceModel\Position;

class Collection extends \Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection
{
    /**
     * Resource initialization
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init('Resume\Career\Model\Position', 'Resume\Career\Model\ResourceModel\Position');
    }
}
