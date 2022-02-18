<?php
/**
 * @author   Mani <kmanidev6@gmail.com>
 * @package Altayer_Support
 * */

namespace Altayer\Support\Model;

use Magento\Framework\Event\ManagerInterface as EventManager;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Model\CustomerManagement;
use Magento\Quote\Model\Quote\Address\ToOrder as ToOrderConverter;
use Magento\Quote\Model\Quote\Address\ToOrderAddress as ToOrderAddressConverter;
use Magento\Quote\Model\Quote\Item\ToOrderItem as ToOrderItemConverter;
use Magento\Quote\Model\Quote\Payment\ToOrderPayment as ToOrderPaymentConverter;
use Magento\Quote\Model\QuoteValidator;
use Magento\Sales\Api\Data\OrderInterfaceFactory as OrderFactory;
use Magento\Sales\Api\OrderManagementInterface as OrderManagement;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory;



/**
 * Class FugitiveOrderProcess
 * @package Altayer\Support\Model
 */
class FugitiveOrderProcess
{

    /**
     * FugitiveOrderProcess constructor.
     * @param CollectionFactory $orderCollectionFactory
     * @param \Magento\Quote\Model\Quote\ItemFactory $quoteItemFactory
     * @param \Magento\Framework\App\ResourceConnection $resource
     * @param \Psr\Log\LoggerInterface $logger
     * @param \Altayer\Quote\Model\QuoteManagement $quoteManagement
     * @param \Magento\Quote\Model\Cart\CartTotalRepository $cartTotalRepository
     * @param \Altayer\SalesRule\Model\AltayerCouponManagement $altayerCouponManagement
     * @param \Magento\Quote\Model\CouponManagement $couponManagement
     * @param \Magento\Quote\Model\BillingAddressManagement $billingAddressManagement
     * @param \Magento\Quote\Model\ShippingMethodManagement $shippingMethodManagement
     * @param \Magento\Checkout\Model\PaymentInformationManagement $paymentInformationManagement
     * @param \Altayer\Quote\Helper\Data $altyerQuoteHelper
     * @param \Magento\Quote\Model\QuoteFactory $quoteFactory
     * @param \Magento\Quote\Api\CartRepositoryInterface $quoteRepository
     * @param \Magento\SalesRule\Model\Rule $ruleFactory
     * @param \Magento\Quote\Api\Data\EstimateAddressInterfaceFactory $estimatedAddressFactory
     * @param \Magento\Quote\Model\Quote\TotalsCollector $totalsCollector
     * @param \Magento\Quote\Model\Cart\ShippingMethodConverter $converter
     * @param \Magento\Quote\Api\CartManagementInterface $cartManagementInterface
     * @param \Magento\Catalog\Model\Product $product
     * @param \Altayer\Quote\Model\QuoteFactory $altayerQuote
     * @param \Magento\Checkout\Model\Session $checkoutSession
     * @param EventManager $eventManager
     * @param QuoteValidator $quoteValidator
     * @param CustomerManagement $customerManagement
     * @param \Magento\Framework\Api\DataObjectHelper $dataObjectHelper
     * @param OrderFactory $orderFactory
     * @param ToOrderConverter $quoteAddressToOrder
     * @param \Magento\Customer\Api\CustomerRepositoryInterface $customerRepository
     * @param ToOrderAddressConverter $quoteAddressToOrderAddress
     * @param ToOrderPaymentConverter $quotePaymentToOrderPayment
     * @param ToOrderItemConverter $quoteItemToOrderItem
     * @param OrderManagement $orderManagement
     * @param \Magento\Sales\Model\Order $order
     */
    public function __construct(
        CollectionFactory $orderCollectionFactory,
        \Magento\Quote\Model\Quote\ItemFactory $quoteItemFactory,
        \Magento\Framework\App\ResourceConnection $resource,
        \Psr\Log\LoggerInterface $logger,
        \Altayer\Quote\Model\QuoteManagement $quoteManagement,
        \Magento\Quote\Model\Cart\CartTotalRepository $cartTotalRepository,
        \Magento\Quote\Model\CouponManagement $couponManagement,
        \Magento\Quote\Model\BillingAddressManagement $billingAddressManagement,
        \Magento\Checkout\Model\PaymentInformationManagement $paymentInformationManagement,
        \Altayer\Quote\Helper\Data $altyerQuoteHelper,
        \Magento\Quote\Model\QuoteFactory $quoteFactory,
        \Magento\Quote\Api\CartRepositoryInterface $quoteRepository,
        \Magento\SalesRule\Model\Rule $ruleFactory,
        \Magento\Quote\Api\Data\EstimateAddressInterfaceFactory $estimatedAddressFactory,
        \Magento\Quote\Model\Quote\TotalsCollector $totalsCollector,
        \Magento\Quote\Model\Cart\ShippingMethodConverter $converter,
        \Magento\Quote\Api\CartManagementInterface $cartManagementInterface,
        \Magento\Catalog\Model\Product $product,
        \Altayer\Quote\Model\QuoteFactory $altayerQuote,
        \Magento\Checkout\Model\Session $checkoutSession,
        EventManager $eventManager,
        QuoteValidator $quoteValidator,
        CustomerManagement $customerManagement,
        \Magento\Framework\Api\DataObjectHelper $dataObjectHelper,
        OrderFactory $orderFactory,
        ToOrderConverter $quoteAddressToOrder,
        \Magento\Customer\Api\CustomerRepositoryInterface $customerRepository,
        ToOrderAddressConverter $quoteAddressToOrderAddress,
        ToOrderPaymentConverter $quotePaymentToOrderPayment,
        ToOrderItemConverter $quoteItemToOrderItem,
        OrderManagement $orderManagement,
        \Magento\Sales\Model\Order $order,
        \Magento\Quote\Api\Data\TotalsInterfaceFactory $totalsFactory
    )
    {
        $this->_orderCollectionFactory = $orderCollectionFactory;
        $this->_resource = $resource;
        $this->logger = $logger;
        $this->_quoteManagement = $quoteManagement;
        $this->_cartTotalRepository = $cartTotalRepository ;
        $this->_couponManagement  = $couponManagement;
        $this->_billingAddressManagement = $billingAddressManagement;
        $this->_paymentInformationManagement = $paymentInformationManagement;
        $this->_altyerQuoteHelper = $altyerQuoteHelper;
        $this->_quoteFactory = $quoteFactory;
        $this->quoteRepository = $quoteRepository;
        $this->ruleFactory = $ruleFactory;
        $this->estimatedAddressFactory = $estimatedAddressFactory;
        $this->totalsCollector = $totalsCollector;
        $this->converter = $converter;
        $this->cartManagementInterface = $cartManagementInterface;
        $this->_product = $product;
        $this->altayerQuote = $altayerQuote;
        $this->checkoutSession = $checkoutSession;
        $this->eventManager = $eventManager;
        $this->quoteValidator = $quoteValidator;
        $this->customerManagement = $customerManagement;
        $this->dataObjectHelper = $dataObjectHelper;
        $this->orderFactory = $orderFactory;
        $this->quoteAddressToOrder = $quoteAddressToOrder;
        $this->customerRepository = $customerRepository;
        $this->quoteAddressToOrderAddress = $quoteAddressToOrderAddress;
        $this->quotePaymentToOrderPayment = $quotePaymentToOrderPayment;
        $this->quoteItemToOrderItem = $quoteItemToOrderItem;
        $this->orderManagement = $orderManagement;
        $this->order = $order;
        $this->totalsFactory = $totalsFactory;
    }

    /**
     * @param $transactionId
     * @return bool|\Magento\Sales\Model\ResourceModel\Order\Collection
     */
    public function getIncrementIdFromTransaction($transactionId)
    {
        try {
            if ($transactionId) {
                $orderCollection = $this->_orderCollectionFactory->create();
                $orderCollection->getSelect()->joinLeft(
                    ['sales_order_payment' => $orderCollection->getTable('sales_order_payment')],
                    'main_table.entity_id = sales_order_payment.parent_id',
                    [
                        "main_table.increment_id",
                        "sales_order_payment.cc_trans_id"
                    ]
                );
                $orderCollection->getSelect()->joinLeft(
                    ['sales_order_address' => $orderCollection->getTable('sales_order_address')],
                    'main_table.entity_id = sales_order_address.parent_id and sales_order_address.address_type = "shipping"',
                    [
                        "main_table.increment_id",
                        "sales_order_payment.cc_trans_id"
                    ]
                )->where('sales_order_payment.cc_trans_id ="' . $transactionId . '"');
                $this->logger->debug("Altayer_Support ::Returning the order collection");
                return $orderCollection;
            }
            $this->logger->debug("Altayer_Support ::not getting any transaction Id");
            return false;
        }catch (\Throwable $e) {
            $this->logger->debug("Altayer_Support ::Getting Increment Id details  :: Error :: " . $e->getMessage() . " - " . $e->getLine());
            throw new \Exception($e->getMessage());
        }

    }

    /**
     * @param $email
     * @param $locale
     * @return array|bool|void
     */
    public function getQuoteDetails($email, $locale)
    {
        try {
            $locale = explode("_", $locale);
            if ($email && array_key_exists(1,$locale)) {
                $connection = $this->_resource->getConnection();
                $sql = "SELECT
                       q.entity_id as quoteId,
                       q.grand_total as grandTotal,
                       q.customer_id as customer_id,
                       (SELECT name FROM store WHERE store_id = q.store_id) AS storeName
                        FROM quote q
                       LEFT JOIN quote_address qa ON (q.entity_id = qa.quote_id)
                       WHERE
                       q.customer_email LIKE '%$email%'
                       AND q.store_id IN (SELECT store_id FROM store WHERE code LIKE '%$locale[1]%')
                       AND q.is_active = 1
                       ORDER BY q.created_at DESC
                       LIMIT 1";
                $records = $connection->fetchAll($sql);
                if (empty($records)) {
                    $this->logger->debug(' Altayer_Support :: Not getting any quote related to this order');
                    return;
                }
                $this->logger->debug(" Altayer_Support :: getting  quote related to this order");
                return $records;
            }
            $this->logger->debug(" Altayer_Support :: not getting mail and locale");
            return false;

        } catch (\Throwable $e) {
            $this->logger->debug(" Altayer_Support :: Getting Quote Details  :: Error :: " . $e->getMessage() . " - " . $e->getLine());
            throw new \Exception($e->getMessage());
        }
    }

    /**
     * @param $email
     * @param $locale
     * @return array|bool|void
     */
    public function getQuoteGuestDeatails($email,$locale){
        try {
            $locale = explode("_", $locale);
            if ($email && array_key_exists(1,$locale)) {
                $connection = $this->_resource->getConnection();
                $sql = "SELECT
                       q.entity_id as quoteId,
                       q.grand_total as grandTotal,
                       q.customer_id as customer_id,
                       (SELECT name FROM store WHERE store_id = q.store_id) AS storeName
                        FROM quote q
                       LEFT JOIN quote_address qa ON (q.entity_id = qa.quote_id)
                       WHERE
                       q.store_id IN (SELECT store_id FROM store WHERE code LIKE '%$locale[1]%')
                       AND q.is_active = 1
                       ORDER BY q.created_at DESC
                       LIMIT 1";
                $records = $connection->fetchAll($sql);
                if (empty($records)) {
                    $this->logger->debug(' Altayer_Support :: Not getting any quote related to this order');
                    return;
                }
                $this->logger->debug(" Altayer_Support :: getting  quote related to this order");
                return $records;
            }
            $this->logger->debug(" Altayer_Support :: not getting mail and locale");
            return false;

        } catch (\Throwable $e) {
            $this->logger->debug(" Altayer_Support :: Getting Quote Details  :: Error :: " . $e->getMessage() . " - " . $e->getLine());
            throw new \Exception($e->getMessage());
        }

    }

    /**
     * @param $quoteId
     * @param $skuOrder
     * @return bool
     * @throws \Exception
     */
    public function checkSkuQuantity($quoteId , $skuOrder ){
        try{
            $quote = $this->altayerQuote->create()->loadByIdWithoutStore($quoteId);
            $skuandQuantityCheck = false;
            if($quote->getItemsCount()) {
                $skuandQuantityCheck = true;
                $inputQuoteSkuQty = [];
                $skuQuantity = explode('/', $skuOrder);
                if(count($skuQuantity))
                {
                    foreach ($skuQuantity as $firstSku) {
                        $firstSkuQuantity = explode('-', $firstSku);
                        if(count($firstSkuQuantity) != 2)
                        {
                            $skuandQuantityCheck = false;
                            break;
                        }
                        $inputSku = $firstSkuQuantity[1];
                        $inputQty = $firstSkuQuantity[0];

                        $inputQuoteSkuQty[$inputSku] = $inputQty;
                    }
                    if( $skuandQuantityCheck){
                        foreach ($quote->getAllVisibleItems() as $item) {
                            if (!array_key_exists($item->getSku(), $inputQuoteSkuQty) || $inputQuoteSkuQty[$item->getSku()] != $item->getQty()) {

                                $skuandQuantityCheck = false;
                                break;
                            }
                        }
                    }

                }else{
                    $skuandQuantityCheck = false;
                }
            }
            $this->logger->debug(" Altayer_Support :: Returning the sku and Quantity check ".$skuandQuantityCheck);
            return $skuandQuantityCheck;
        }catch (\Throwable $e) {
            $this->logger->debug(" Altayer_Support :: Getting Sku and Quantity check  :: Error :: " . $e->getMessage() . " - " . $e->getLine());
            throw new \Exception($e->getMessage());
        }

    }

    /**
     * @param $quoteId
     * @return array|bool|void
     */
    public function getMaskedDetails($quoteId)
    {
        try {
            if ($quoteId) {
                $connection = $this->_resource->getConnection();
                $sql = "SELECT masked_id as maskedId
                              FROM quote_id_mask
                              WHERE quote_id = ${quoteId}
                              LIMIT 1";
                $records = $connection->fetchAll($sql);
                if (empty($records)) {
                    $this->logger->debug('Altayer_Support :: Not getting any Mask Id related to this order');
                    return;
                }
                return $records;

            }
            $this->logger->debug('Altayer_Support :: Not getting any quoteId for masked details');
            return false;
        } catch (\Throwable $e) {
            $this->logger->debug(" Altayer_Support :: Getting Maked Details  :: Error :: " . $e->getMessage() . " - " . $e->getLine());
            throw new \Exception($e->getMessage());
        }

    }

    /**
     * @param $quoteId
     * @return array|bool|void
     */
    public function getCustomerTokenFromQuote($quoteId)
    {
        try {
            if ($quoteId) {
                $connection = $this->_resource->getConnection();
                $sql = "select token
                        from oauth_token
                     where customer_id = (
                     select customer_id
                     from quote
                     where entity_id = ${quoteId}
                        )
                     limit 1";
                $records = $connection->fetchAll($sql);
                if (empty($records)) {
                    $this->logger->debug('Altayer_Support :: Not getting any customer token related to this order');
                    return;
                }
                return $records;

            }
            $this->logger->debug(" Altayer_Support :: not getting quoteId");
            return false;
        } catch (\Throwable $e) {
            $this->logger->debug("Altayer_Support :: Getting Customer Token for login user :: Error :: " . $e->getMessage() . " - " . $e->getLine());
            throw new \Exception($e->getMessage());
        }
    }

    /**
     * @param $cartId
     * @param $amountPaid
     * @return bool
     */
    public function manageGrandTotal($cartId , $amountPaid){
        try {
            if ($cartId && $amountPaid){
                $quote =  $this->_quoteFactory->create()
                    ->getCollection()
                    ->addFieldToFilter('entity_id',$cartId)
                    ->getFirstItem();
                $grandTotalData = $quote->getData();
                $grandTotal = $grandTotalData['base_grand_total'];
                if($grandTotal && $grandTotal != $amountPaid )
                {
                    $this->logger->debug("User Paid but amount paid".$amountPaid ."and GrandTotal".$grandTotal ."are not equal");

                }
                $this->logger->debug("Altayer_Support :: returning the Base Grand Total");
                return $grandTotal;

            }
            $this->logger->debug("Altayer_Support :: not getting cartId and amount paid");
            return false;
        }catch (\Throwable $e) {
            $this->logger->debug("Altayer_Support :: Getting Manage Grand Total for login user :: Error :: " . $e->getMessage() . " - " . $e->getLine());
            throw new \Exception($e->getMessage());
        }

    }

    /**
     * @param $cartId
     * @return \Magento\Quote\Api\Data\TotalsInterface
     * @throws \Exception
     */
    public function cartReconcilationAmount($cartId)
    {
        try {
            /** @var \Magento\Quote\Model\Quote $quote */
            $quote = $this->altayerQuote->create()->loadByIdWithoutStore($cartId);
            if ($quote->isVirtual()) {
                $addressTotalsData = $quote->getBillingAddress()->getData();
                $addressTotals = $quote->getBillingAddress()->getTotals();
            } else {
                $addressTotalsData = $quote->getShippingAddress()->getData();
                $addressTotals = $quote->getShippingAddress()->getTotals();
            }

            /** @var \Magento\Quote\Api\Data\TotalsInterface $quoteTotals */
            $quoteTotals = $this->totalsFactory->create();
            $this->dataObjectHelper->populateWithArray(
                $quoteTotals,
                $addressTotalsData,
                '\Magento\Quote\Api\Data\TotalsInterface'
            );
            $items = [];
            foreach ($quote->getAllVisibleItems() as $index => $item) {
                $items[$index] = $this->itemConverter->modelToDataObject($item);
            }
            $calculatedTotals = $this->totalsConverter->process($addressTotals);
            $quoteTotals->setTotalSegments($calculatedTotals);

            $amount = $quoteTotals->getGrandTotal() - $quoteTotals->getTaxAmount();
            $amount = $amount > 0 ? $amount : 0;
            $quoteTotals->setCouponCode($this->couponService->get($cartId));
            $quoteTotals->setGrandTotal($amount);
            $quoteTotals->setItems($items);
            $quoteTotals->setItemsQty($quote->getItemsQty());
            $quoteTotals->setBaseCurrencyCode($quote->getBaseCurrencyCode());
            $quoteTotals->setQuoteCurrencyCode($quote->getQuoteCurrencyCode());
            return $quoteTotals;
        }catch (\Throwable $e) {
            $this->logger->debug("Altayer_Support :: cart Reconcilatio nAmount For login user :: Error :: " . $e->getMessage() . " - " . $e->getLine());
            throw new \Exception($e->getMessage());
        }


    }

    /**
     * @param $cartId
     * @param $billingAddress
     * @param $transactionId
     * @param $cardNumber
     * @param $cardType
     * @param $cardTypeName
     * @param $cardExpiryDate
     * @param $cardExpiryMonth
     * @param $cardExpiryYear
     * @return bool|int|null
     */
    public function saveAndPlaceOrder($cartId, $transactionId, $cardNumber, $cardType, $cardTypeName, $cardExpiryDate, $cardExpiryMonth, $cardExpiryYear,$referenceNumber,$amountPaid){
        try {
            $message = "";
            $quote = $this->altayerQuote->create()->loadByIdWithoutStore($cartId);
            $quote->setStore($quote->getStore());
            $quote->getBillingAddress()->addData($quote->getBillingAddress()->getData());
            $quote->getShippingAddress()->addData($quote->getShippingAddress()->getData());
            $quote->setPaymentMethod('cybersource_rest'); //payment method
            $quote->setInventoryProcessed(false); //not effetive inventory
            // Set Sales Order Payment
            //$quote->getPayment()->importData(['method' => 'cybersource_rest']);
            $quote->save(); //Now Save quote and your quote is ready
            $quote->collectTotals();  // Collect Totals
            $grandTotal = $quote->getBaseGrandTotal();
            $this->logger->debug("User Paid ".$amountPaid ."and GrandTotal".$grandTotal);
            if($quote->getBaseGrandTotal() && $quote->getBaseGrandTotal() != $amountPaid )
            {
                $this->logger->debug("User Paid but amount paid".$amountPaid ."and GrandTotal".$grandTotal ."are not equal");
                if($amountPaid < $grandTotal)
                {
                    $couponAmount = round($grandTotal - $amountPaid,2);
                    $couponCode = $this->generateFixedCoupon($cartId, $couponAmount, $quote);
                    $setCouponCode = $this->setQuoteCouponCode($cartId,$couponCode,$quote);
                    if($quote->getBaseGrandTotal() != $grandTotal)
                    {
                        $reconciledTotalWithCoupon = $this->cartReconcilationAmount($cartId);
                        $this->logger->debug("Altayer_Support :: Cart total after reconcile with coupon".$reconciledTotalWithCoupon ."does match the amount paid".$amountPaid );
                    }
                    $this->logger->debug("Altayer_Support :: returning the coupon amount for login");
                }

            }
            // Create Order From Quote
            $orderId = $this->fugitivePlaceOrder($quote->getId(),$transactionId,$cardNumber,$cardType,$cardTypeName,$cardExpiryDate,$cardExpiryMonth,$cardExpiryYear,$referenceNumber);
            $order = $this->order->load($orderId);
            $order->setEmailSent(0);
            $increment_id = $order->getRealOrderId();
            if($order->getEntityId()){
                $this->logger->debug("Altayer_Support ::returning the increment Id of order for login user ");
                return "Order Created with Increment Id ".$order->getIncrementId();
            }
            $this->logger->debug("Altayer_Support ::There some might error in creating the order for login users ");
            $message ="There some might error in creating the order for registered user";
            return $message;
        }catch (\Throwable $e) {
            $this->logger->debug("Altayer_Support :: Getting Place Order for login user  :: Error :: " . $e->getMessage() . " - " . $e->getLine());
            throw new \Exception($e->getMessage());
        }
    }


    /**
     * @param $quoteId
     * @param $amount
     * @param $quote
     * @return mixed
     */
    public function generateFixedCoupon($quoteId, $amount, $quote)
    {
        try {
            $cartPriceRule = $this->ruleFactory;
            $data = [
                'name'=>'API_AUTO_GENERATED_' . date('Y-m-d'),
                'description'=>'Fixed Discount Coupon',
                'from_date'=>date('Y-m-d'),
                'to_date'=>'',
                'uses_per_customer'=>1,
                'uses_per_coupon'=>1,
                'customer_group_ids'=>array($quote->getCustomerGroupId()),
                'is_active'=> 1,
                'simple_action'=>'cart_fixed',
                'discount_amount'=>$amount,
                'apply_to_shipping'=>'no',
                'times_used'=>1,
                'website_ids'=>array($quote->getStore()->getWebsiteId()),
                'coupon_type'=>2,
                'coupon_code'=>"API-GENERATED-" . $quoteId,
                'approved_by'=>'Auto Approved',
                'team_requested'=>'Backend',
                'finance_mapping'=>'Fugitive',
                'responsibility'=>'Digital',
                'reason_for_creation'=>'Fugitive'
            ];
            $cartPriceRule->setData($data);
            $cartPriceRule->save();
            $couponData = $cartPriceRule->getData();

            return $couponData['coupon_code'];
        }catch (\Throwable $e) {
            $this->logger->debug("Altayer_Support :: Generate Coupon for login user  :: Error :: " . $e->getMessage() . " - " . $e->getLine());
            throw new \Exception($e->getMessage());
        }

    }

    /**
     * @param $cartId
     * @param $couponCode
     * @param $quote
     * @return bool
     */
    public function setQuoteCouponCode($cartId, $couponCode, $quote)
    {
        try {
            $quote->setCouponCode($couponCode);
            $this->quoteRepository->save($quote->collectTotals());
            return true;
        }catch (\Throwable $e) {
            $this->logger->debug("Altayer_Support :: Set Coupon Code in Quote for login user:: Error :: " . $e->getMessage() . " - " . $e->getLine());
            throw new \Exception($e->getMessage());
        }


    }

    /**
     * @param $cartId
     * @param $transactionId
     * @param $cardNumber
     * @param $cardType
     * @param $cardTypeName
     * @param $cardExpiryDate
     * @param $cardExpiryMonth
     * @param $cardExpiryYear
     * @return mixed
     */
    public function fugitivePlaceOrder($cartId,
                                       $transactionId,
                                       $cardNumber,
                                       $cardType,
                                       $cardTypeName,
                                       $cardExpiryDate,
                                       $cardExpiryMonth,
                                       $cardExpiryYear,
                                       $referenceNumber
    ){
        try
        {
            $quote = $this->altayerQuote->create()->loadByIdWithoutStore($cartId);
            $quote->getPayment()->setQuote($quote);
            $data = ['method' => 'cybersource_rest',
                'additional_data'=>[
                    'cc_trans_id'=> $transactionId,
                    'decision' => 'ACCEPT',
                    'card_number'=> "xxxxxxxxxxxx".$cardNumber,
                    'card_type'=> $cardType,
                    'card_type_name'=> $cardTypeName,
                    'card_expiry_date' => $cardExpiryDate,
                    'card_expiry_month'=> $cardExpiryMonth,
                    'card_expiry_year'=> $cardExpiryYear,
                    'reference_number'=> $referenceNumber
                ]
            ];
            if (isset($data['additional_data'])) {
                $data = array_merge($data, (array)$data['additional_data']);
                unset($data['additional_data']);
            }

            $quote->getPayment()->importData($data);
            $this->eventManager->dispatch('checkout_submit_before', ['quote' => $quote]);
            $order = $this->submit($quote);
            if (null == $order) {
                throw new LocalizedException(__('Cannot place order.'));
            }

            $this->checkoutSession->setLastQuoteId($quote->getId());
            $this->checkoutSession->setLastSuccessQuoteId($quote->getId());
            $this->checkoutSession->setLastOrderId($order->getId());
            $this->checkoutSession->setLastRealOrderId($order->getIncrementId());
            $this->checkoutSession->setLastOrderStatus($order->getStatus());

            $this->eventManager->dispatch('checkout_submit_all_after', ['order' => $order, 'quote' => $quote]);
            return $order->getId();
        }catch (\Throwable $e) {
            $this->logger->debug("Altayer_Support :: Get Error for fugitivePlaceOrder for login user :: Error :: " . $e->getMessage() . " - " . $e->getLine());
            throw new \Exception($e->getMessage());
        }

    }

    /**
     * @param $quote
     * @param array $orderData
     * @return \Magento\Sales\Api\Data\OrderInterface|null
     */
    public function submit($quote, $orderData = [])
    {
        try {
            if (!$quote->getAllVisibleItems()) {
                $quote->setIsActive(false);
                return null;
            }
            return $this->submitQuote($quote, $orderData);
        }catch (\Throwable $e) {
            $this->logger->debug("Altayer_Support :: Get Error for Submit Quote for login user:: Error :: " . $e->getMessage() . " - " . $e->getLine());
            throw new \Exception($e->getMessage());
        }

    }

    /**
     * @param $quote
     * @param array $orderData
     * @return \Magento\Sales\Api\Data\OrderInterface
     */
    protected function submitQuote($quote, $orderData = [])
    {
        try {
            $order = $this->orderFactory->create();
            $this->quoteValidator->validateBeforeSubmit($quote);
            if (!$quote->getCustomerIsGuest()) {
                if ($quote->getCustomerId()) {
                    $this->_prepareCustomerQuote($quote);
                }
                $this->customerManagement->populateCustomerInfo($quote);
            }
            $addresses = [];
            $quote->reserveOrderId();
            if ($quote->isVirtual()) {
                $this->dataObjectHelper->mergeDataObjects(
                    '\Magento\Sales\Api\Data\OrderInterface',
                    $order,
                    $this->quoteAddressToOrder->convert($quote->getBillingAddress(), $orderData)
                );
            } else {
                $this->dataObjectHelper->mergeDataObjects(
                    '\Magento\Sales\Api\Data\OrderInterface',
                    $order,
                    $this->quoteAddressToOrder->convert($quote->getShippingAddress(), $orderData)
                );
                $shippingAddress = $this->quoteAddressToOrderAddress->convert(
                    $quote->getShippingAddress(),
                    [
                        'address_type' => 'shipping',
                        'email' => $quote->getCustomerEmail()
                    ]
                );
                $addresses[] = $shippingAddress;
                $order->setShippingAddress($shippingAddress);
                $order->setShippingMethod($quote->getShippingAddress()->getShippingMethod());
            }
            $billingAddress = $this->quoteAddressToOrderAddress->convert(
                $quote->getBillingAddress(),
                [
                    'address_type' => 'billing',
                    'email' => $quote->getCustomerEmail()
                ]
            );
            $addresses[] = $billingAddress;
            $order->setBillingAddress($billingAddress);
            $order->setAddresses($addresses);
            $order->setPayment($this->quotePaymentToOrderPayment->convert($quote->getPayment()));
            $order->setItems($this->resolveItems($quote));
            if ($quote->getCustomer()) {
                $order->setCustomerId($quote->getCustomer()->getId());
            }
            $order->setQuoteId($quote->getId());
            $order->setCustomerEmail($quote->getCustomerEmail());
            $order->setCustomerFirstname($quote->getCustomerFirstname());
            $order->setCustomerMiddlename($quote->getCustomerMiddlename());
            $order->setCustomerLastname($quote->getCustomerLastname());

            $this->eventManager->dispatch(
                'sales_model_service_quote_submit_before',
                [
                    'order' => $order,
                    'quote' => $quote
                ]
            );
            try {
                $order = $this->orderManagement->place($order);
                $quote->setIsActive(false);
                $this->eventManager->dispatch(
                    'sales_model_service_quote_submit_success',
                    [
                        'order' => $order,
                        'quote' => $quote
                    ]
                );
                $this->quoteRepository->save($quote);
            } catch (\Exception $e) {
                $this->eventManager->dispatch(
                    'sales_model_service_quote_submit_failure',
                    [
                        'order'     => $order,
                        'quote'     => $quote,
                        'exception' => $e
                    ]
                );
                $this->logger->debug("Altayer_Support :: Get Error for Fugitive sales_model_service_quote_submit_failure");
                throw $e;
            }
            return $order;
        }catch (\Throwable $e) {
            $this->logger->debug("Altayer_Support :: Get Error for Fugitive Submit Quote for login user:: Error :: " . $e->getMessage() . " - " . $e->getLine());
            throw new \Exception($e->getMessage());
        }

    }

    /**
     * @param $quote
     */
    protected function _prepareCustomerQuote($quote)
    {
        try {
            /** @var Quote $quote */
            $billing = $quote->getBillingAddress();
            $shipping = $quote->isVirtual() ? null : $quote->getShippingAddress();

            $customer = $this->customerRepository->getById($quote->getCustomerId());
            $hasDefaultBilling = (bool)$customer->getDefaultBilling();
            $hasDefaultShipping = (bool)$customer->getDefaultShipping();

            if ($shipping && !$shipping->getSameAsBilling()
                && (!$shipping->getCustomerId() || $shipping->getSaveInAddressBook())
            ) {
                $shippingAddress = $shipping->exportCustomerAddress();
                if (!$hasDefaultShipping) {
                    //Make provided address as default shipping address
                    $shippingAddress->setIsDefaultShipping(true);
                    $hasDefaultShipping = true;
                }
                $quote->addCustomerAddress($shippingAddress);
                $shipping->setCustomerAddressData($shippingAddress);
            }

            if (!$billing->getCustomerId() || $billing->getSaveInAddressBook()) {
                $billingAddress = $billing->exportCustomerAddress();
                if (!$hasDefaultBilling) {
                    //Make provided address as default shipping address
                    if (!$hasDefaultShipping) {
                        //Make provided address as default shipping address
                        $billingAddress->setIsDefaultShipping(true);
                    }
                    $billingAddress->setIsDefaultBilling(true);
                }
                $quote->addCustomerAddress($billingAddress);
                $billing->setCustomerAddressData($billingAddress);
            }
            if ($shipping && !$shipping->getCustomerId() && !$hasDefaultBilling) {
                $shipping->setIsDefaultBilling(true);
            }
        }catch (\Throwable $e) {
            $this->logger->debug("Altayer_Support :: Get Error for Prepare Customer Quotefor login user:: Error :: " . $e->getMessage() . " - " . $e->getLine());
            throw new \Exception($e->getMessage());
        }

    }

    /**
     * @param $quote
     * @return array
     */
    protected function resolveItems($quote)
    {
        try {
            $quoteItems = [];
            foreach ($quote->getAllItems() as $quoteItem) {
                /** @var \Magento\Quote\Model\ResourceModel\Quote\Item $quoteItem */
                $quoteItems[$quoteItem->getId()] = $quoteItem;
            }
            $orderItems = [];
            foreach ($quoteItems as $quoteItem) {
                $parentItem = (isset($orderItems[$quoteItem->getParentItemId()])) ?
                    $orderItems[$quoteItem->getParentItemId()] : null;
                $orderItems[$quoteItem->getId()] =
                    $this->quoteItemToOrderItem->convert($quoteItem, ['parent_item' => $parentItem]);
            }
            return array_values($orderItems);
        }catch (\Throwable $e) {
            $this->logger->debug("Altayer_Support :: Get Error for Resolve Items for login user :: Error :: " . $e->getMessage() . " - " . $e->getLine());
            throw new \Exception($e->getMessage());
        }

    }
}
