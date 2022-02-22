<?php

namespace Resume\Career\Block\Adminhtml\Career\Edit\Tab\Renderer;

use Magento\Framework\DataObject;
class Url extends \Magento\Backend\Block\Widget\Grid\Column\Renderer\AbstractRenderer
{
	protected $_storeManager;


    public function __construct(
        \Magento\Backend\Block\Context $context,
        \Magento\Store\Model\StoreManagerInterface $storeManager,      
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->_storeManager = $storeManager;        
    }
    public function render(DataObject $row)
    {
        $resumeName = $row->getResume();        
        $mediaUrl = $this->_storeManager->getStore()->getBaseUrl(
           \Magento\Framework\UrlInterface::URL_TYPE_MEDIA
       ).''.$resumeName;
        return '<a target="_blank" href="'.$mediaUrl.'"> Download Resume</a>';
    }
}
