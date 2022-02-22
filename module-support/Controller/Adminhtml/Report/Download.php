<?php

namespace Altayer\Support\Controller\Adminhtml\Report;

use Magento\Backend\App\Action;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Psr\Log\LoggerInterface;
use Magento\Backend\App\Action\Context;

class Download extends Action
{


    /**
     * Logger Interface
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var ScopeConfigInterface
     */
    protected $_scopeConfig;

    public function __construct(
        Context $context,
        LoggerInterface $logger,
        ScopeConfigInterface $scopeConfig
    )
    {
        $this->logger = $logger;
        $this->_scopeConfig = $scopeConfig;
        parent::__construct($context);
    }
    
    
    public function execute()
    {
        /** @var \Magento\Backend\Model\View\Result\Redirect $resultRedirect */
        $resultRedirect = $this->resultRedirectFactory->create();
        // check if we know what should be deleted
        $id = $this->getRequest()->getParam('report_id');
        if ($id) {
            try {
                // init model and delete
                $model = $this->_objectManager->create('Altayer\Support\Model\Report')->load($id);
                $reportSql = $model->getReportSql();

                $model->load($id);

                return $resultRedirect->setPath('*/*/');
            } catch (\Exception $e) {
                // display error message
                $this->messageManager->addError($e->getMessage());
                // go back to edit form
                return $resultRedirect->setPath('*/*/edit', ['report_id' => $id]);
            }
        }
        // display error message
        $this->messageManager->addError(__('We can\'t find the Report to delete.'));
        // go to grid
        return $resultRedirect->setPath('*/*/');
    }

    protected function validateSQL($sql){
        
    }
}