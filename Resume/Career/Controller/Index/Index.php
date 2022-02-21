<?php
namespace Resume\Career\Controller\Index;


class Index extends \Magento\Framework\App\Action\Action
{

    
    protected $_megamenuFactory;
    
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Framework\Controller\Result\RawFactory $resultRawFactory
    ) {
        //$this->_megamenuFactory = $megamenuFactory;
        parent::__construct($context);
    }
    
    public function execute()
    {
      $this->_view->loadLayout();
      $this->_view->renderLayout();
    }
}
