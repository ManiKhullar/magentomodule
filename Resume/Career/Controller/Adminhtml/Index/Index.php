<?php
namespace Resume\Career\Controller\Adminhtml\Index;

use Magento\Backend\App\Action\Context;
use Magento\Framework\View\Result\PageFactory;

class Index extends \Magento\Backend\App\Action
{
	/**
     * @var PageFactory
     */
    protected $resultPageFactory;

    /**
     * @param Context $context
     * @param PageFactory $resultPageFactory
     */
    public function __construct(
        Context $context,
        PageFactory $resultPageFactory
    ) {
        parent::__construct($context);
        $this->resultPageFactory = $resultPageFactory;
    }
	
    /**
     * Check the permission to run it
     *
     * @return bool
     */
    protected function _isAllowed()
    {
        return $this->_authorization->isAllowed('Resume_Career::career_manage');
    }

    /**
     * News List action
     *
     * @return void
     */
    public function execute()
    {
		/** @var \Magento\Backend\Model\View\Result\Page $resultPage */
        $resultPage = $this->resultPageFactory->create();
        $resultPage->setActiveMenu(
            'Resume_Career::career_manage'
        )->addBreadcrumb(
            __('Applicant List'),
            __('Applicant List')
        )->addBreadcrumb(
            __('Manage Career'),
            __('Manage Career')
        );
        $resultPage->getConfig()->getTitle()->prepend(__('Applicant List'));
		return $resultPage;
    }
}
