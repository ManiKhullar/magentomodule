<?php
/**
 * Interest Resource Collection
 */
namespace Resume\Career\Model\ResourceModel\Interest;

class Collection extends \Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection
{
    /**
     * Resource initialization
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init('Resume\Career\Model\Interest', 'Resume\Career\Model\ResourceModel\Interest');
    }
}
