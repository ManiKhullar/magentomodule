<?php
/**
 * @author Amrendra Singh <amrendragr8@gmail.com>
 * @package Altayer_Support
 * */

namespace Altayer\Support\Model;

use Magento\Framework\Event\ManagerInterface as EventManager;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\State\InvalidTransitionException;
use Magento\Quote\Model\Cart\Totals\ItemConverter;
use Magento\Quote\Model\Cart\TotalsConverter;
use Magento\Quote\Model\CustomerManagement;
use Magento\Quote\Model\Quote\Address\ToOrder as ToOrderConverter;
use Magento\Quote\Model\Quote\Address\ToOrderAddress as ToOrderAddressConverter;
use Magento\Quote\Model\Quote\Item\ToOrderItem as ToOrderItemConverter;
use Magento\Quote\Model\Quote\Payment\ToOrderPayment as ToOrderPaymentConverter;
use Magento\Quote\Model\QuoteValidator;
use Magento\Sales\Api\Data\OrderInterfaceFactory as OrderFactory;
use Magento\Sales\Api\OrderManagementInterface as OrderManagement;
use Magento\Quote\Api\CouponManagementInterface;

/**
 * Class FugitiveGuestOrder
 * @package Altayer\Support\Model
 */
class FugitiveGuestOrder
{
    const METHOD_GUEST = 'guest';

    /**
     * FugitiveGuestOrder constructor.
     * @param \Psr\Log\LoggerInterface $logger
     * @param \Altayer\Quote\Model\GuestCart\GuestCartRepository $guestCartRepository
     * @param \Magento\Quote\Model\QuoteFactory $quoteFactory
     * @param \Magento\Quote\Model\Cart\CartTotalRepository $cartTotalRepository
     * @param \Magento\SalesRule\Model\Rule $ruleFactory
     * @param \Altayer\Quote\Model\QuoteFactory $altayerQuote
     * @param OrderFactory $orderFactory
     * @param QuoteValidator $quoteValidator
     * @param CustomerManagement $customerManagement
     * @param \Magento\Framework\Api\DataObjectHelper $dataObjectHelper
     * @param ToOrderConverter $quoteAddressToOrder
     * @param ToOrderPaymentConverter $quotePaymentToOrderPayment
     * @param ToOrderItemConverter $quoteItemToOrderItem
     * @param \Magento\Quote\Api\CartRepositoryInterface $quoteRepository
     * @param EventManager $eventManager
     * @param ToOrderAddressConverter $quoteAddressToOrderAddress
     * @param OrderManagement $orderManagement
     * @param \Magento\Checkout\Model\Session $checkoutSession
     * @param \Magento\Sales\Model\Order $order
     */
    public function __construct(
        \Psr\Log\LoggerInterface $logger,
        \Altayer\Quote\Model\GuestCart\GuestCartRepository $guestCartRepository,
        \Magento\Quote\Model\QuoteFactory $quoteFactory,
        \Magento\Quote\Model\Cart\CartTotalRepository $cartTotalRepository,
        \Magento\SalesRule\Model\Rule $ruleFactory,
        \Altayer\Quote\Model\QuoteFactory $altayerQuote,
        OrderFactory $orderFactory,
        QuoteValidator $quoteValidator,
        CustomerManagement $customerManagement,
        \Magento\Framework\Api\DataObjectHelper $dataObjectHelper,
        ToOrderConverter $quoteAddressToOrder,
        ToOrderPaymentConverter $quotePaymentToOrderPayment,
        ToOrderItemConverter $quoteItemToOrderItem,
        \Magento\Quote\Api\CartRepositoryInterface $quoteRepository,
        EventManager $eventManager,
        ToOrderAddressConverter $quoteAddressToOrderAddress,
        OrderManagement $orderManagement,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Sales\Model\Order $order,
        \Magento\Quote\Api\Data\TotalsInterfaceFactory $totalsFactory,
        ItemConverter $converter,
        TotalsConverter $totalsConverter,
        \Magento\Customer\Model\CustomerFactory $customerFactory,
        \Magento\Customer\Api\CustomerRepositoryInterface $customerRepository,
        CouponManagementInterface $couponService
    )
    {
        $this->logger = $logger;
        $this->_quoteFactory = $quoteFactory;
        $this->_cartTotalRepository = $cartTotalRepository ;
        $this->ruleFactory = $ruleFactory;
        $this->altayerQuote = $altayerQuote;
        $this->orderFactory = $orderFactory;
        $this->quoteValidator = $quoteValidator;
        $this->customerManagement = $customerManagement;
        $this->dataObjectHelper = $dataObjectHelper;
        $this->quoteAddressToOrder = $quoteAddressToOrder;
        $this->quotePaymentToOrderPayment = $quotePaymentToOrderPayment;
        $this->quoteItemToOrderItem = $quoteItemToOrderItem;
        $this->quoteRepository = $quoteRepository;
        $this->eventManager = $eventManager;
        $this->quoteAddressToOrderAddress = $quoteAddressToOrderAddress;
        $this->orderManagement = $orderManagement;
        $this->checkoutSession = $checkoutSession;
        $this->order = $order;
        $this->totalsFactory = $totalsFactory;
        $this->itemConverter = $converter;
        $this->totalsConverter = $totalsConverter;
        $this->customerFactory = $customerFactory;
        $this->customerRepository = $customerRepository;
        $this->couponService = $couponService;
    }


    /**
     * @param $cartId
     * @param $amountPaid
     * @return bool|false|float|mixed|null
     * @throws \Exception
     */
    public function manageGrandTotalForGuest($cartId, $amountPaid)
    {
        try {
            $message = "";
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
                $this->logger->debug("Altayer_Support :: returning the Base grand Total");
                return $grandTotal;
            }
            $this->logger->debug("Altayer_Support :: not getting the cartId or amount Paid");
            return false;
        }catch (\Throwable $e) {
            $this->logger->debug("Altayer_Support :: Getting Manage Grand Total For Guest :: Error :: " . $e->getMessage() . " - " . $e->getLine());
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
            $this->logger->debug(" Altayer_Support :: Getting cart Reconcilation Amount For Guest :: Error :: " . $e->getMessage() . " - " . $e->getLine());
            throw new \Exception($e->getMessage());
        }


    }

    /**
     * @param $quoteId
     * @param $email
     * @return bool
     * @throws \Exception
     */
    public function isShippingAddressExists($quoteId, $email)
    {
        try{
            $quote = $this->altayerQuote->create()->loadByIdWithoutStore($quoteId);
            if($quote->getId())
            {
                if($quote->getShippingAddress()->getId())
                {

                    if($quote->getShippingAddress()->getEmail()==$email)
                    {
                        return true;
                    }
                }
                return false;
            }
            return false;
        }catch (\Throwable $e) {
            $this->logger->debug(" Altayer_Support :: Getting shipping address For Guest :: Error :: " . $e->getMessage() . " - " . $e->getLine());
            throw new \Exception($e->getMessage());
        }

    }
    /**
     * @param $quoteId
     * @param $couponAmount
     * @param $quote
     * @return mixed
     */
    public function generateFixedCouponForGuest($quoteId, $couponAmount, $quote)
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
                'discount_amount'=>$couponAmount,
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
            $this->logger->debug("Altayer_Support :: Generate Coupon For Guest :: Error :: " . $e->getMessage() . " - " . $e->getLine());
            throw new \Exception($e->getMessage());
        }
    }

    /**
     * @param $cartId
     * @param $couponCode
     * @param $quote
     * @return bool
     */
    public function setQuoteCouponCodeForGuest($cartId, $couponCode, $quote)
    {
        try {
            $quote->setCouponCode($couponCode);
            $this->quoteRepository->save($quote->collectTotals());
            return true;
        }catch (\Throwable $e) {
            $this->logger->debug("Altayer_Support :: Set Coupon Code in Quote:: Error :: " . $e->getMessage() . " - " . $e->getLine());
            throw new \Exception($e->getMessage());
        }

    }

    /**
     * @param $quoteId
     * @param $transactionId
     * @param $cardNumber
     * @param $cardType
     * @param $cardTypeName
     * @param $cardExpiryDate
     * @param $cardExpiryMonth
     * @param $cardExpiryYear
     * @param $email
     * @return bool|int|null
     */
    public function saveAndPlaceOrderForGuest($quoteId, $transactionId, $cardNumber, $cardType, $cardTypeName, $cardExpiryDate, $cardExpiryMonth, $cardExpiryYear, $email,$referenceNumber,$amountPaid)
    {
        try {
            $message = "";
            $quote = $this->altayerQuote->create()->loadByIdWithoutStore($quoteId);
            $quote->setStore($quote->getStore());
            //Set Address to quote
            $quote->getBillingAddress()->addData($quote->getBillingAddress()->getData());
            $quote->getShippingAddress()->addData($quote->getShippingAddress()->getData());
            $quote->setPaymentMethod('cybersource_rest'); //payment method
            $quote->setInventoryProcessed(false); //not effetive inventory
            $quote->collectTotals();  // Collect Totals
            $quote->save();
            $grandTotal = $quote->getBaseGrandTotal();
            $this->logger->debug("User Paid ".$amountPaid ."and GrandTotal".$grandTotal);
            if($quote->getBaseGrandTotal() && $quote->getBaseGrandTotal() != $amountPaid )
            {
                $this->logger->debug("User Paid but amount paid".$amountPaid ."and GrandTotal".$grandTotal ."are not equal");
                if($amountPaid < $grandTotal)
                {
                    $couponAmount = round($grandTotal - $amountPaid,2);
                    $couponCode = $this->generateFixedCouponForGuest($quoteId, $couponAmount, $quote);
                    $setCouponCode = $this->setQuoteCouponCodeForGuest($quoteId,$couponCode,$quote);
                    if($quote->getBaseGrandTotal() != $grandTotal)
                    {
                        $reconciledTotalWithCoupon = $this->cartReconcilationAmount($cartId);
                        $this->logger->debug("Altayer_Support :: Cart total after reconcile with coupon".$reconciledTotalWithCoupon ."does match the amount paid".$amountPaid );
                    }
                    $this->logger->debug("Altayer_Support :: returning the coupon amount for login");
                }

            }
            // Create Order From Quote
            $orderId = $this->fugitivePlaceOrderForGuest($quote->getId(),$transactionId, $cardNumber, $cardType, $cardTypeName, $cardExpiryDate, $cardExpiryMonth, $cardExpiryYear,$email,$referenceNumber);
            $order = $this->order->load($orderId);
            $order->setEmailSent(0);
            $increment_id = $order->getRealOrderId();
            if($order->getEntityId()){
                $this->logger->debug("Altayer_Support ::returning the increment Id of order ");
                return "Order Created with Increment Id ".$order->getIncrementId();
            }
            $message ="There some might error in creating the order for guest";
            $this->logger->debug("Altayer_Support ::There some might error in creating the order for guest ");
            return $message;
        }catch (\Throwable $e) {
            $this->logger->debug("Altayer_Support :: Getting Place Order :: Error :: " . $e->getMessage() . " - " . $e->getLine());
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
     * @param $email
     * @return mixed
     */
    public function fugitivePlaceOrderForGuest($cartId, $transactionId, $cardNumber, $cardType, $cardTypeName, $cardExpiryDate, $cardExpiryMonth, $cardExpiryYear, $email,$referenceNumber)
    {
        try {
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
            //set the additional data
            if (isset($data['additional_data'])) {
                $data = array_merge($data, (array)$data['additional_data']);
                unset($data['additional_data']);
            }
            //Import the data to payment
            $quote->getPayment()->importData($data);
            if ($quote->isVirtual()) {
                $quote->getBillingAddress()->setPaymentMethod($quote->getPayment()->getMethod());
            } else {
                // check if shipping address is set
                if ($quote->getShippingAddress()->getCountryId() === null) {
                    throw new InvalidTransitionException(__('Shipping address is not set'));
                }
            }

            $quote->setTotalsCollectedFlag(false)->collectTotals()->save();
            $quote->getPayment()->getId();
            if ($quote->getCheckoutMethod() === self::METHOD_GUEST) {
                $quote->setCustomerId(null);
                $quote->setCustomerEmail($quote->getBillingAddress()->getEmail());
                $quote->setCustomerIsGuest(true);
                $quote->setCustomerGroupId(\Magento\Customer\Api\Data\GroupInterface::NOT_LOGGED_IN_ID);
            }

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
            $this->logger->debug("Altayer_Support :: Getting Fugitive place order for guest :: Error :: " . $e->getMessage() . " - " . $e->getLine());
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
            $this->logger->debug("Altayer_Support :: Get Error for Submit Quote:: Error :: " . $e->getMessage() . " - " . $e->getLine());
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
            $customer = $this->customerFactory->create();
            $customer->setWebsiteId($quote->getStore()->getWebsiteId());
            $customer->loadByEmail($quote->getShippingAddress()->getEmail());
            if(!$customer->getEntityId())
            {
                $customer->setWebsiteId(($quote->getStore()->getWebsiteId()))
                    ->setStore($quote->getStore())
                    ->setFirstname($quote->getShippingAddress()->getFirstname())
                    ->setLastname($quote->getShippingAddress()->getLastname())
                    ->setEmail($quote->getShippingAddress()->getEmail())
                    ->setPassword($quote->getShippingAddress()->getEmail());
                $customer->save();
            }

            $customer= $this->customerRepository->getById($customer->getEntityId());
            $quote->assignCustomer($customer);
            $this->quoteValidator->validateBeforeSubmit($quote);
            if (!$quote->getCustomerIsGuest()) {
                if ($quote->getCustomerId()) {
                    $this->_prepareCustomerQuote($quote);
                }
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
                        'email' => $quote->getShippingAddress()->getCustomerEmail()
                    ]
                );
                $quote->getShippingAddress()->setShippingAmount($quote->getShippingAddress()->getShippingAmount());
                $addresses[] = $shippingAddress;
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
                throw $e;
            }
            return $order;
        }catch (\Throwable $e) {
            $this->logger->debug("Altayer_Support :: Get Error for Fugitive Submit Quote:: Error :: " . $e->getMessage() . " - " . $e->getLine());
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
            $this->logger->debug("Altayer_Support :: Get Error for Prepare Customer Quote:: Error :: " . $e->getMessage() . " - " . $e->getLine());
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
            $this->logger->debug("Altayer_Support :: Get Error for Resolve Items:: Error :: " . $e->getMessage() . " - " . $e->getLine());
            throw new \Exception($e->getMessage());
        }

    }

}