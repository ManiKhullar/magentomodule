<?php
namespace Resume\Career\Model;


class Interest extends \Magento\Framework\Model\AbstractModel
{
   
    protected function _construct()
    {
        $this->_init('Resume\Career\Model\ResourceModel\Interest');
    }

}
