<?php
namespace Resume\Career\Model\ResourceModel;

/**
 * News Resource Model
 */
class Position extends \Magento\Framework\Model\ResourceModel\Db\AbstractDb
{
    /**
     * Initialize resource model
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init('resume_career_position', 'position_id');
    }
}
