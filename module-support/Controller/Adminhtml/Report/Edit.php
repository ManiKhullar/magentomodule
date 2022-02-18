<?php

namespace Altayer\Support\Controller\Adminhtml\Report;

use Magento\Framework\App\ResponseInterface;
use Magento\Backend\App\Action;

class Edit extends Action
{

    protected $_coreRegistry;
    protected $_resultPageFactory;
    protected $_reportFactory;

    /**
     * @param \Magento\Backend\App\Action\Context $context
     * @param \Magento\Framework\Registry $coreRegistry
     */
    public function __construct(
        \Magento\Backend\App\Action\Context $context,
        \Magento\Framework\Registry $coreRegistry,
        \Altayer\Support\Model\ReportFactory $reportFactory,
        \Magento\Framework\View\Result\PageFactory $resultPageFactory
    )
    {
        $this->_coreRegistry = $coreRegistry;
        $this->_resultPageFactory = $resultPageFactory;
        $this->_reportFactory = $reportFactory;
        parent::__construct($context);
    }



    /**
     * Dispatch request
     *
     * @return \Magento\Framework\Controller\ResultInterface|ResponseInterface
     * @throws \Magento\Framework\Exception\NotFoundException
     */
    public function execute() 
    {
        $reportId = (int)$this->getRequest()->getParam('report_id');
        /**@var \Altayer\Support\Model\Report $reportModel */
        $reportModel = $this->_reportFactory->create();
        $reportModel->getResource()->load($reportModel, $reportId, 'report_id');

        if ($reportId) {
            if (!$reportModel->getId()) {
                $this->messageManager->addErrorMessage(__('This store does not exist anymore.'));
                $this->_redirect('*/*/');
                return;
            } else {
                $reportName = $reportModel->getData("report_name");
                $this->_coreRegistry->register('report_model', $reportModel);
            }
        }
        $resultPage = $this->_resultPageFactory->create();

        $title = $reportId ? __('Edit ' . $reportName . ' Report ') : __('Add New Report ');

        //$resultPage->setActiveMenu('Altayer_Support::report');
        $resultPage->getConfig()->getTitle()->prepend($title);

        return $resultPage;
    }
}