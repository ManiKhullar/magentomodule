<?php
/**
 * @author Amrendra Singh <amrendragr8@gmail.com>
 * @package Altayer_Support
 * */
namespace Altayer\Support\Controller\Adminhtml\Fugitive;

use Magento\Backend\App\Action\Context;
use Magento\Framework\View\Result\PageFactory;
use Magento\Backend\App\Action;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Psr\Log\LoggerInterface;

class Createorder extends Action
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

    /**
     * @var \Magento\Framework\Controller\Result\JsonFactory
     */
    protected $resultJsonFactory;

    /**
     * @var \Magento\Backend\App\Action\Context
     */
    protected $context;

    /**
     * @var  \Altayer\Support\Helper\FugitiveHelper
     */
    protected $fugitiveHelper;

    /**
     * Createorder constructor.
     * @param LoggerInterface $logger
     * @param ScopeConfigInterface $scopeConfig
     * @param Context $context
     * @param PageFactory $resultPageFactory
     * @param \Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory
     * @param \Altayer\Support\Helper\FugitiveHelper $fugitiveHelper
     */
    public function __construct(
        LoggerInterface $logger,
        ScopeConfigInterface $scopeConfig,
        Context $context,
        PageFactory $resultPageFactory,
        \Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory,
        \Altayer\Support\Helper\FugitiveHelper $fugitiveHelper
    )
    {
        $this->resultPageFactory = $resultPageFactory;
        $this->logger = $logger;
        $this->_scopeConfig = $scopeConfig;
        $this->resultJsonFactory = $resultJsonFactory;
        $this->_fugitiveHelper = $fugitiveHelper;
        parent::__construct($context);
    }


    /**
     * Index Action*
     * @return void
     */
    public function execute()
    {
        try {
            /** @var \Magento\Framework\Controller\Result\Json $resultJson */
            $resultJson = $this->resultJsonFactory->create();
            $createOrderFromQuote = $this->_fugitiveHelper->createOrderFromQuote();
            if($createOrderFromQuote && substr($createOrderFromQuote,0,13)=="Order Created")
            {
                $response = ['success' => 'true','orderresponse'=> $createOrderFromQuote,'ordersuccess'=>1];
            }
            else{
                $response = ['success' => 'true','orderresponse'=> $createOrderFromQuote,'ordersuccess'=>0];
            }
            $resultJson->setData($response);
        } catch (\Exception $e) {
            $this->logger->debug("Altayer_Support :: Create Order :: Error :: " . $e->getMessage() . " - " . $e->getLine());
            $response = ['error' => 'true', 'message' => $e->getMessage()];
        }
        $this->_actionFlag->set('', self::FLAG_NO_POST_DISPATCH, true);
        return $resultJson->setData($response);
    }
}