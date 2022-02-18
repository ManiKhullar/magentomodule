<?php
/**
 * @author   Mani <kmanidev6@gmail.com>
 * @package Altayer_Support
 * */
namespace Altayer\Support\Controller\Adminhtml\Fugitive;

use Magento\Backend\App\Action\Context;
use Magento\Framework\View\Result\PageFactory;
use Magento\Backend\App\Action;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Psr\Log\LoggerInterface;

class Checkincrementid extends Action
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

    public function __construct(
        LoggerInterface $logger,
        ScopeConfigInterface $scopeConfig,
        Context $context,
        PageFactory $resultPageFactory,
        \Altayer\Support\Model\FugitiveOrderProcess $fugitiveOrderProcess,
        \Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory
    )
    {
        $this->resultPageFactory = $resultPageFactory;
        $this->logger = $logger;
        $this->_scopeConfig = $scopeConfig;
        $this->_fugitiveOrderProcess = $fugitiveOrderProcess;
        $this->resultJsonFactory = $resultJsonFactory;
        parent::__construct($context);
    }


    /**
     * Index Action*
     * @return void
     */
    public function execute()
    {
        try {
            $incrementId = false;
            /** @var \Magento\Framework\Controller\Result\Json $resultJson */
            $resultJson = $this->resultJsonFactory->create();
            $ccTransactionId = $this->getRequest()->getPostValue('cc_transaction_id');
            $sku = $this->getRequest()->getPostValue('skus');
            if($ccTransactionId)
            {
                $orderCollection = $this->_fugitiveOrderProcess->getIncrementIdFromTransaction($ccTransactionId);
                if($orderCollection && $orderCollection->getSize()){
                    $orderData = $orderCollection->getFirstItem();
                    $incrementId = $orderData['increment_id'];
                    $response = ['success' => 'true','incrementId'=>$incrementId];
                }else{
                    $response = ['success' => 'true','incrementId'=>$incrementId];
                }
            }else{
                $response = ['success' => 'true','incrementId'=>'Please enter the transaction Id'];
            }

            $resultJson->setData($response);
        } catch (\Exception $e) {
            $this->logger->debug("Altayer_Support :: Check IncrementId   :: Error :: " . $e->getMessage() . " - " . $e->getLine());
            $response = ['error' => 'true', 'message' => $e->getMessage()];
        }
        $this->_actionFlag->set('', self::FLAG_NO_POST_DISPATCH, true);
        return $resultJson->setData($response);
    }

}
