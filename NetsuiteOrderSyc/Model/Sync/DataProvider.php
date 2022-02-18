<?php
/**
 * Copyright © Echidna Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Echidna\NetsuiteOrderSyc\Model\Sync;

use Echidna\NetsuiteOrderSyc\Model\Config;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Sales\Api\OrderRepositoryInterface;
use \Magento\Customer\Api\CustomerRepositoryInterface;

class DataProvider
{
    const country = '_unitedStates';

    /**
     * @var OrderRepositoryInterface
     */
    protected OrderRepositoryInterface $_orderRepository;
    /**
     * @var CustomerRepositoryInterface
     */
    protected CustomerRepositoryInterface $customerRepositoryInterface;

    /**
     * @param Config $config
     * @param OrderRepositoryInterface $orderRepository
     * @param CustomerRepositoryInterface $customerRepositoryInterface
     */
    public function __construct(
        Config                      $config,
        OrderRepositoryInterface    $orderRepository,
        CustomerRepositoryInterface $customerRepositoryInterface
    )
    {
        $this->config = $config;
        $this->_orderRepository = $orderRepository;
        $this->customerRepositoryInterface = $customerRepositoryInterface;
    }

    /**
     * @param $order
     * @param $websiteId
     * @return mixed|void
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function prepareOrderSycArray($order, $websiteId)
    {
        $isEnable = $this->config->isNetsuiteOrderSyscEnabled($websiteId);
        if ($isEnable) {
            $order = $this->_orderRepository->get($order->getId());
            $requestData = [
                'entity' => [
                    'type' => 'customer',
                    'internalId' => $this->getCustomerInternalId($order, $websiteId)
                ],
                'tranDate' => $this->formattedOrderDate($order->getCreatedAt()),
                'shipMethod' => [
                    'internalId' => $this->getShippingMethodInternalId($this->config->getShippingMethod($websiteId), $order->getShippingMethod())
                ],
                'shippingCost' => $order->getShippingAmount(),
                'otherRefNum' => $order->getPayment()->getPoNumber() ?? $order->getIncrementId(),
                'customFieldList' => [
                    'customField' => [
                        [
                            'fieldType' => 'StringCustomFieldRef',
                            'scriptId' => $this->config->getCustomFieldScriptId($websiteId),
                            'value' => $order->getPayment()->getPoNumber() ?? $order->getIncrementId()
                        ]
                    ]
                ],
                'location' => [
                    'internalId' => $this->getLocationInternalId($order, $websiteId),
                    'type' => 'location'
                ],
            ];
            $requestData = $this->addItem($order, $requestData);
            if ((int)$order->getBaseTaxAmount() > 0) {
                $requestData = $this->addTaxItem($order->getBaseTaxAmount(), $requestData, $websiteId);
            }
            if ((int)$order->getDiscountAmount()) {
                $requestData = $this->addDiscountAmount($requestData, $websiteId);
                $requestData['discountRate'] = $order->getDiscountAmount();
            }
            $requestData = $this->setBillingInfo($order, $requestData);
            $requestData = $this->setShippingInfo($order, $requestData);
            $requestData = $this->setTerms($websiteId, $requestData, $order);
            return $requestData;
        }
    }

    /**
     * @param $orderDate
     * @return string
     */
    public function formattedOrderDate($orderDate)
    {
        $datetime = \DateTime::createFromFormat("Y-m-d H:i:s", $orderDate);
        return $datetime->format(\DateTime::RFC3339);
    }

    /**
     * @param $order
     * @param $websiteId
     * @return mixed
     */
    public function getCustomerInternalId($order, $websiteId)
    {
        if (!$this->config->getCustomerInternalId($websiteId)) {
            $customerId = $order->getCustomerId();
            $customer = $this->customerRepositoryInterface->getById($customerId);
            return $customer->getCustomAttribute('customer_netsuite_internal_id')->getValue();
        }
        return $this->config->getCustomerInternalId($websiteId);
    }

    /**
     * @param $shippingMethodJson
     * @param $shippingMethod
     * @return mixed|string
     */
    public function getShippingMethodInternalId($shippingMethodJson, $shippingMethod)
    {
        foreach ($shippingMethodJson as $source) {
            if ($source['shipping_method'] === $shippingMethod) {
                return $source['shipping_method_internal_id'];
            }
        }
        return '';
    }

    /**
     * @param $order
     * @param $requestData
     * @return array
     */
    public function addItem($order, $requestData)
    {
        if ($requestData) {
            $orderedItemArray = [];
            foreach ($order->getItems() as $item) {
                if (isset($orderedItemArray[$item->getProduct()->getNetsuiteInternalId()])) {
                    $orderedItemArray[$item->getProduct()->getNetsuiteInternalId()]['ordered_qty'] += (int)$item->getQtyOrdered();
                    $orderedItemArray[$item->getProduct()->getNetsuiteInternalId()]['product_price'] = $item->getPrice();
                } else {
                    $orderedItemArray[$item->getProduct()->getNetsuiteInternalId()]['ordered_qty'] = (int)$item->getQtyOrdered();
                    $orderedItemArray[$item->getProduct()->getNetsuiteInternalId()]['product_price'] = $item->getPrice();
                }
            }
            if (!empty($orderedItemArray)) {
                $requestData = $this->getOrderItemArray($orderedItemArray, $requestData);
            }
        }
        return $requestData;
    }

    /**
     * @param $taxAmount
     * @param $requestData
     * @return array
     */
    public function addTaxItem($taxAmount, $requestData, $websiteId)
    {
        if ($requestData) {
            $requestData['itemList']['item'][] = [
                'quantity' => 1,
                'rate' => $taxAmount,
                'amount' => $taxAmount,
                'item' => [
                    'internalId' => $this->config->getTaxProductInternalId($websiteId) //49540
                ],
                'price' => [
                    'internalId' => -1,
                ]
            ];
        }
        return $requestData;
    }

    /**
     * @param $requestData
     * @param $websiteId
     * @return mixed
     */
    public function addDiscountAmount($requestData, $websiteId)
    {
        if ($requestData) {
            $requestData['discountItem'] = [
                'internalId' => $this->config->getDiscountItemInternalId($websiteId),
            ];
        }
        return $requestData;
    }

    /**
     * @param $orderItemArray
     * @param $requestData
     * @return array|string
     */
    public function getOrderItemArray($orderItemArray, $requestData)
    {
        if (!empty($orderItemArray) && !empty($requestData)) {
            foreach ($orderItemArray as $key => $item) {
                $requestData['itemList']['item'][] = [
                    'quantity' => (string)$item['ordered_qty'],
                    'rate' => (string)$item['product_price'],
                    'item' => [
                        'type' => 'inventoryItem',
                        'internalId' => $key
                    ],
                    'price' => [
                        'internalId' => -1,
                        'type' => 'priceLevel'
                    ]
                ];
            }
            return $requestData;
        }
        return '';
    }

    /**
     * @param $order
     * @param $requestData
     * @return array
     */
    public function setShippingInfo($order, $requestData)
    {
        if ($requestData) {
            $requestData['shippingAddress'] = [
                'country' => self::country,
                'addressee' => $order->getShippingAddress()->getName(),
                'addr1' => $order->getShippingAddress()->getStreet()[0],
                'city' => $order->getShippingAddress()->getCity(),
                'state' => $order->getShippingAddress()->getRegionCode(),
                'zip' => $order->getShippingAddress()->getPostcode(),
                'addrPhone' => $order->getShippingAddress()->getTelephone(),
                'override' => true
            ];
        }
        return $requestData;
    }

    /**
     * @param $order
     * @param $requestData
     * @return array
     */
    public function setBillingInfo($order, $requestData)
    {
        if ($requestData) {
            $requestData['billingAddress'] = [
                'country' => self::country,
                'addressee' => $order->getBillingAddress()->getName(),
                'addr1' => $order->getBillingAddress()->getStreet()[0],
                'city' => $order->getBillingAddress()->getCity(),
                'state' => $order->getBillingAddress()->getRegionCode(),
                'zip' => $order->getBillingAddress()->getPostcode(),
                'addrPhone' => $order->getBillingAddress()->getTelephone(),
                'override' => true
            ];
        }
        return $requestData;
    }

    /**
     * @param $order
     * @param $websiteId
     * @return int
     */
    public function getLocationInternalId($order, $websiteId)
    {
        $locationInternalId = 0;
        $fixLocationData = $this->config->getFixLocationInternalId($websiteId);
        $shippingMethod = $order->getShippingMethod();
        if (!empty($fixLocationData)) {
            foreach ($fixLocationData as $location) {
                if ($location['shipping_method'] == $shippingMethod) {
                    $locationInternalId = $location['location_internal_id'];
                    break;
                }
            }
        }
        if ($locationInternalId == 0) {
            $shippingPostCode = $order->getShippingAddress()->getPostCode();
            $locationData = $this->config->getLocationInternalId($websiteId);
            if (!empty($locationData)) {
                foreach ($locationData as $location) {
                    if ($location['netsuite_zipcode'] == $shippingPostCode) {
                        $locationInternalId = $location['netsuite_zipcode_internal_id'];
                        break;
                    }
                }
            }
        }
        return (int)$locationInternalId;
    }

    /**
     * @param $order
     * @param $orderNetsuiteInternalId
     * @return void
     */
    public function setOrderNetsuiteInternalId($order, $orderNetsuiteInternalId)
    {
        $order = $this->_orderRepository->get($order->getId());
        $order->setData('netsuit_order_id', $orderNetsuiteInternalId);
        $this->_orderRepository->save($order);
    }

    /**
     * @param $websiteId
     * @param $requestData
     * @param $order
     * @return mixed
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function setTerms($websiteId, $requestData, $order)
    {
        $isEnableTermsOrderSyscEnabled = $this->config->isTermsOrderSyscEnabled($websiteId);
        if ($requestData && $isEnableTermsOrderSyscEnabled) {
            $isTermsInternalId = $this->config->getTermsInternalId($websiteId);
            if (!$this->config->getTermsInternalId($websiteId)) {
                $customerId = $order->getCustomerId();
                $customer = $this->customerRepositoryInterface->getById($customerId);
                $requestData['terms'] = [
                    'internalId' => $customer->getCustomAttribute('terms')->getValue(),
                ];
                return $requestData;
            }
            $requestData['terms'] = [
                'internalId' => $isTermsInternalId,
            ];
        }
        return $requestData;
    }
}
