<?php
namespace Resume\Career\Model\ResourceModel;

/**
 * News Resource Model
 */
class Career extends \Magento\Framework\Model\ResourceModel\Db\AbstractDb
{
    /**
     * Initialize resource model
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init('resume_career', 'career_id');
    }
}
