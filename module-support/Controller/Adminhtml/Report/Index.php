<?php
namespace Altayer\Support\Controller\Adminhtml\Report;

use Magento\Backend\App\Action\Context;
use Magento\Framework\View\Result\PageFactory;
use Magento\Backend\App\Action;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Psr\Log\LoggerInterface;

class Index extends Action
{
    /**
     * @var PageFactory
     */
    protected $resultPageFactory;

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
        LoggerInterface $logger,
        ScopeConfigInterface $scopeConfig,
        Context $context,
        PageFactory $resultPageFactory
    )
    {
        $this->resultPageFactory = $resultPageFactory;
        $this->logger = $logger;
        $this->_scopeConfig = $scopeConfig;
        parent::__construct($context);
    }


    /**
     * Index Action*
     * @return void
     */
    public function execute()
    {
        /** @var \MAgento\Backend\Model\View\Result\Page $resultPage */
        $resultPage = $this->resultPageFactory->create();
        $resultPage->getConfig()->getTitle()->prepend(__('Altayer Report'));
        return $resultPage;
    }

    /**
     * Check Grid List Permission.
     *
     * @return bool
     */
    protected function _isAllowed()
    {
        return $this->_authorization->isAllowed('Magento_Backend::content');
    }
}
