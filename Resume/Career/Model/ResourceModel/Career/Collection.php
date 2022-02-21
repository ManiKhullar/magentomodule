<?php
/**
 * Career Resource Collection
 */
namespace Resume\Career\Model\ResourceModel\Career;

class Collection extends \Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection
{
    /**
     * Resource initialization
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init('Resume\Career\Model\Career', 'Resume\Career\Model\ResourceModel\Career');
    }
}
