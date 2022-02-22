<?php
namespace Resume\Career\Model;


class Position extends \Magento\Framework\Model\AbstractModel
{
   
    protected function _construct()
    {
        $this->_init('Resume\Career\Model\ResourceModel\Position');
    }

}
