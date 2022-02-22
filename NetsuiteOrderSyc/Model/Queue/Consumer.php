<?php
/**
 * Copyright © Echidna Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Echidna\NetsuiteOrderSyc\Model\Queue;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Notification\NotifierInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Psr\Log\LoggerInterface;
use Echidna\Netsuite\Model\Api\RequestBuilder;
use Echidna\Netsuite\Model\Api\Service;
use Echidna\NetsuiteOrderSyc\Model\Config;
use Echidna\NetsuiteOrderSyc\Model\Sync\DataProvider;

class Consumer
{
    /**
     * @var OrderRepositoryInterface
     */
    protected OrderRepositoryInterface $_orderRepository;
    /**
     * @var NotifierInterface
     */
    protected NotifierInterface $_notifier;
    /**
     * @var LoggerInterface
     */
    protected LoggerInterface $_logger;
    /**
     * @var RequestBuilder
     */
    protected RequestBuilder $searchBuilder;
    /**
     * @var Service
     */
    protected Service $apiService;
    /**
     * @var Config
     */
    private Config $config;

    /**
     * @var DataProvider
     */
    private DataProvider $dataProvider;


    /**
     * @param OrderRepositoryInterface $orderRepository
     * @param NotifierInterface $notifier
     * @param RequestBuilder $searchBuilder
     * @param Service $apiService
     * @param Config $config
     * @param DataProvider $dataProvider
     * @param LoggerInterface $logger
     */
    public function __construct(
        OrderRepositoryInterface $orderRepository,
        NotifierInterface        $notifier,
        RequestBuilder           $searchBuilder,
        Service                  $apiService,
        Config                   $config,
        DataProvider             $dataProvider,
        LoggerInterface          $logger
    )
    {
        $this->_orderRepository = $orderRepository;
        $this->_notifier = $notifier;
        $this->searchBuilder = $searchBuilder;
        $this->apiService = $apiService;
        $this->config = $config;
        $this->dataProvider = $dataProvider;
        $this->_logger = $logger;
    }

    /**
     * @param $orderData
     * @return void
     */
    public function process($orderData)
    {
        try {
            $this->execute($orderData);
            $this->_notifier->addMajor(
                __('Your queue are ready'),
                __('You can check your orders at Salesforce Queue page')
            );
        } catch (\Exception $e) {
            $errorCode = $e->getCode();
            $message = __('Sorry, something went wrong during add order to queue. Please see log for details.');
            $this->_notifier->addCritical(
                $errorCode,
                $message
            );
            $this->_logger->critical($errorCode . ": " . $message);
        }
    }

    /**
     * @param $orderData
     * @return void
     * @throws LocalizedException
     */
    private function execute($orderData)
    {
        $storeId = 0;
        if (!empty($orderData->getOrderId())) {
            $orderId = $orderData->getOrderId();
            $order = $this->_orderRepository->get($orderId);
            $websiteId = $order->getStore()->getWebsiteId();
            $isEnable = $this->config->isNetsuiteOrderSyscEnabled($websiteId);
            if ($isEnable) {
                $requestData = $this->dataProvider->prepareOrderSycArray($order, $websiteId);
                $requestObject = $this->searchBuilder->prepareAddRequestObject("salesOrder", $requestData);
                $response = $this->apiService->addRecord($requestObject, $storeId);
                if($orderNetsuiteInternalId = $response->internalId){
                    $this->setOrderNetsuiteInternalId($order,$orderNetsuiteInternalId);
                }
            }
        }
    }

    /**
     * @param $order
     * @param $orderNetsuiteInternalId
     * @return void
     */
    public function setOrderNetsuiteInternalId($order, $orderNetsuiteInternalId)
    {
        $order = $this->_orderRepository->get($order->getId());
        $order->setData('netsuit_order_id',$orderNetsuiteInternalId);
        $this->_orderRepository->save($order);
    }
}
