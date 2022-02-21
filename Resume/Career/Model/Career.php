<?php
namespace Resume\Career\Model;


class Career extends \Magento\Framework\Model\AbstractModel
{
   
    protected function _construct()
    {
        $this->_init('Resume\Career\Model\ResourceModel\Career');
    }

}
