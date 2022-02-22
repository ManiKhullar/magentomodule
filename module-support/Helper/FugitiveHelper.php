<?php


namespace Altayer\Support\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;

/**
 * Class FugitiveHelper
 * @package Altayer\Support\Helper
 */
class FugitiveHelper extends AbstractHelper
{
    const FUGITIVE_ORDER_CREATION ='altayer_order_monitor/Fugitive_order/create_fugitive';

    /**
     * @var \Altayer\Support\Model\FugitiveGuestOrder
     */
    protected $fugitiveGuestOrder;

    /**
     * @var \Altayer\Support\Model\FugitiveOrderProcess
     */
    protected $fugitiveOrderProcess;

    /**
     * @var Context
     */
    protected $context;


    /**
     * FugitiveHelper constructor.
     * @param Context $context
     * @param \Altayer\Support\Model\FugitiveOrderProcess $fugitiveOrderProcess
     * @param \Altayer\Support\Model\FugitiveGuestOrder $fugitiveGuestOrder
     * @param \Magento\Framework\App\Request\Http $request
     */
    public function __construct(
        Context $context,
        \Altayer\Support\Model\FugitiveOrderProcess $fugitiveOrderProcess,
        \Altayer\Support\Model\FugitiveGuestOrder $fugitiveGuestOrder,
        \Magento\Framework\App\Request\Http $request
    )
    {
        parent::__construct($context);
        $this->logger = $context->getLogger();
        $this->_fugitiveOrderProcess = $fugitiveOrderProcess;
        $this->_fugitiveGuestOrder = $fugitiveGuestOrder;
        $this->_request = $request;
    }

    /**
     * @return bool|int|null
     */
    public function createOrderFromQuote(){
        try {
            $message = "";
            /**
             * checking the params
             */
            $checkParams = $this->checkParams($this->_request->getParams());
            if(!$checkParams)
            {
                $message = "Please Enter All Details ";
                return $message;
            }
            /**
             * checking the fugitive order configuration
             */
            if(!$this->enableFugitiveOrderCreation())
            {
                $message = 'Please Enable the Fugitive Order Creation ';
                return $message;
            }
            /**
             * fetching the params
             */
            $email =  $this->_request->getParam('email-id');
            $locale =  $this->_request->getParam('forstores');
            $grandTotal =  $this->_request->getParam('grandtotal');
            $transactionId =  $this->_request->getParam('cc-transactionId');
            $cardNumber =  $this->_request->getParam('cardnumber');
            $cardType =  $this->_request->getParam('forcardTypes');
            if($cardType=='MasterCard')
            {
                $cardType = '002';
            }elseif($cardType=='Visa')
            {
                $cardType = '001';
            }
            $cardExpiryDate = $this->_request->getParam('expiry');
            $cardTypeName =  $this->_request->getParam('forcardTypes');
            $skusOrder = $this->_request->getParam('skus-createorder');
            $cardDetails = explode('/',$cardExpiryDate);
            $cardExpiryMonth = $cardDetails[0];
            $cardExpiryYear = $cardDetails[1];
            $referenceNumber =$this->_request->getParam('referenceNumber');
            /**
             * checking the transactionId existance
             */
            if($transactionId)
            {
                $orderCollection = $this->_fugitiveOrderProcess->getIncrementIdFromTransaction($transactionId);
                if($orderCollection && $orderCollection->getSize())
                {
                    $message = "There is  already order created  with this transactionId :: ".$transactionId;
                    return $message;
                }
            }

            /**
             * getting details for quote
             */
            if($email && $locale)
            {
                $quoteDetails = $this->_fugitiveOrderProcess->getQuoteDetails($email,$locale);
                $quoteDetailsForGuest = $this->_fugitiveOrderProcess->getQuoteGuestDeatails($email,$locale);
            }

            /**
             * order creation for registered user
             */
            if($quoteDetails && array_key_exists(0,$quoteDetails) )
            {
                /**
                 * checking the sku and quantity
                 */
                $checkSkuQuantity = $this->_fugitiveOrderProcess->checkSkuQuantity($quoteDetails[0]['quoteId'],$skusOrder);
                if($checkSkuQuantity)
                {
                    $quoteData = $quoteDetails[0];
                    if(array_key_exists('customer_id',$quoteData))
                    {
                        $customerId = $quoteData['customer_id'];
                        $quoteId = $quoteData['quoteId'];
                        $customerToken = $this->_fugitiveOrderProcess->getCustomerTokenFromQuote($quoteId);
                    }
                    if(array_key_exists('quoteId',$quoteData))
                    {
                        $quoteId = $quoteData['quoteId'];
                        $maskedDetails = $this->_fugitiveOrderProcess->getMaskedDetails($quoteId);
                    }
                    if (array_key_exists('quoteId',$quoteData))
                    {
                        $quoteId= $quoteData['quoteId'];
                        $manageGrandTotals = $this->_fugitiveOrderProcess->manageGrandTotal($quoteId,$grandTotal);
                        if($manageGrandTotals)
                        {
                            $placeOrder = $this->_fugitiveOrderProcess->saveAndPlaceOrder($quoteId,
                                $transactionId,
                                $cardNumber,
                                $cardType,
                                $cardTypeName,
                                $cardExpiryDate,
                                $cardExpiryMonth,
                                $cardExpiryYear,
                                $referenceNumber,
                                $grandTotal);
                            return $placeOrder;
                        }
                    }
                    $message = "For Login QuoteId is not available";
                    return $message;
                }
                $message = "please check the sku and quantity";
                return $message;
            }elseif($quoteDetailsForGuest && array_key_exists(0,$quoteDetailsForGuest))
            {
                $checkShippingAddressForGuest = false;
                $quoteData = $quoteDetailsForGuest[0];
                if(array_key_exists('quoteId',$quoteData)){
                    $quoteId = $quoteData['quoteId'];
                    $checkShippingAddressForGuest = $this->_fugitiveGuestOrder->isShippingAddressExists($quoteId,$email);
                }
                /**
                 * checking the shipping email existance
                 */
                if($checkShippingAddressForGuest){
                    /**
                     * checking the sku and quantity
                     */
                    $checkSkuQuantity = $this->_fugitiveOrderProcess->checkSkuQuantity($quoteDetailsForGuest[0]['quoteId'],$skusOrder);
                    if($checkSkuQuantity)
                    {
                        $quoteData = $quoteDetailsForGuest[0];
                        if(array_key_exists('quoteId',$quoteData))
                        {
                            $quoteId = $quoteData['quoteId'];
                            $maskedDetails = $this->_fugitiveOrderProcess->getMaskedDetails($quoteId);
                        }
                        /**
                         * checking the masked details
                         */
                        if($maskedDetails && array_key_exists('0',$maskedDetails))
                        {
                            $maskedData = $maskedDetails['0'];
                            if(array_key_exists('maskedId',$maskedData))
                            {
                                $maskedId = $maskedDetails['0']['maskedId'];
                                if(array_key_exists('quoteId',$quoteData))
                                {
                                    $quoteId= $quoteData['quoteId'];
                                    $manageGrandTotalsForGuest = $this->_fugitiveGuestOrder->manageGrandTotalForGuest($quoteId,$grandTotal);
                                    if($manageGrandTotalsForGuest)
                                    {
                                        $placeOrderForGuest = $this->_fugitiveGuestOrder->saveAndPlaceOrderForGuest($quoteId,
                                            $transactionId,
                                            $cardNumber,
                                            $cardType,
                                            $cardTypeName,
                                            $cardExpiryDate,
                                            $cardExpiryMonth,
                                            $cardExpiryYear,
                                            $email,
                                            $referenceNumber,
                                            $grandTotal);
                                        return $placeOrderForGuest;
                                    }
                                }
                                $message = "For guest QuoteId is not available";
                                return $message;
                            }
                            $message = "Masked Id is not available";
                            return $message;
                        }
                    }
                    $message = "please check the sku and quantity";
                    return $message;
                }

            }
            $message = "There is not any active quote";
            return $message;
        }catch (\Throwable $e) {
            $this->logger->debug(" Altayer_Support :: Create Order From Quote :: Error :: " . $e->getMessage() . " - " . $e->getLine());
            throw new \Exception($e->getMessage());
        }

    }

    /**
     * @param $params
     * @return bool
     */
    public function checkParams($params)
    {
        try {
            if(!empty($params['email-id']) &&
                !empty($params['forstores']) &&
                !empty($params['grandtotal'])&&
                !empty($params['cc-transactionId'])&&
                !empty($params['cardnumber'])&&
                !empty($params['forcardTypes'])&&
                !empty($params['skus-createorder'])&&
                !empty($params['expiry'] &&
                    !empty($params['referenceNumber']))
            )
            {
                return true;
            }
            return false;
        }catch (\Throwable $e) {
            $this->logger->debug("Altayer_Support :: Check Params  :: Error :: " . $e->getMessage() . " - " . $e->getLine());
            throw new \Exception($e->getMessage());
        }

    }

    /**
     * @return mixed
     */
    public function enableFugitiveOrderCreation()
    {
        return $this->scopeConfig->getValue(self::FUGITIVE_ORDER_CREATION);
    }


}
