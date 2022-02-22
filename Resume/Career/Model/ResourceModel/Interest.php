<?php
namespace Resume\Career\Model\ResourceModel;

/**
 * News Resource Model
 */
class Interest extends \Magento\Framework\Model\ResourceModel\Db\AbstractDb
{
    /**
     * Initialize resource model
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init('resume_career_interest', 'interest_id');
    }
}
