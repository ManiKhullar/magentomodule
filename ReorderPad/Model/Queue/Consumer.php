<?php
/**
 * Copyright © Echidna Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Echidna\ReorderPad\Model\Queue;

use Magento\Catalog\Model\ProductFactory;
use Magento\Framework\Filesystem;
use Magento\Framework\Notification\NotifierInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\RequisitionList\Model\RequisitionListItem\SaveHandler;
use Magento\Sales\Api\OrderRepositoryInterface;
use Psr\Log\LoggerInterface;
use Magento\RequisitionList\Model\RequisitionListFactory;

/**
 * Class Consumer
 * @package Echidna\ReorderPad\Model\Queue
 */
class Consumer
{
    /**
     * @var OrderRepositoryInterface
     */
    protected $_orderRepository;
    /**
     * @var NotifierInterface
     */
    protected $_notifier;
    /**
     * @var Filesystem
     */
    protected $filesystem;
    /**
     * @var null
     */
    protected $_type = null;
    /**
     * @var SaveHandler
     */
    private $requisitionListItemSaveHandler;
    /**
     * @var ProductFactory
     */
    protected $productFactory;
    /**
     * @var RequisitionListFactory
     */
    protected $requisitionListFactory;

    /**
     * @param LoggerInterface $logger
     * @param OrderRepositoryInterface $orderRepository
     * @param NotifierInterface $notifier
     * @param Filesystem $filesystem
     * @param Json $json
     * @param SaveHandler $requisitionListItemSaveHandler
     * @param ProductFactory $productFactory
     * @param RequisitionListFactory $requisitionListFactory
     */
    public function __construct(
        LoggerInterface          $logger,
        OrderRepositoryInterface $orderRepository,
        NotifierInterface        $notifier,
        Filesystem               $filesystem,
        Json                     $json,
        SaveHandler              $requisitionListItemSaveHandler,
        ProductFactory           $productFactory,
        RequisitionListFactory   $requisitionListFactory
    )
    {
        $this->_logger = $logger;
        $this->_orderRepository = $orderRepository;
        $this->_notifier = $notifier;
        $this->filesystem = $filesystem;
        $this->_json = $json;
        $this->requisitionListItemSaveHandler = $requisitionListItemSaveHandler;
        $this->productFactory = $productFactory;
        $this->requisitionListFactory = $requisitionListFactory;
    }

    /**
     * @param $orderData
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
     * @param $orderId
     */
    private function execute($orderData)
    {
        if (!empty($orderData->getOrderId())) {
            $order = $this->_orderRepository->get($orderData->getOrderId());
            $itemId = 0;
            foreach ($order->getItems() as $item) {
                $preparedProductData = new \Magento\Framework\DataObject();
                $options = [];
                $product = $this->productFactory->create()->load($item->getProductId());
                $preparedProductData->setSku($product->getSku());
                $option = [
                    'product' => $item->getProductId(),
                    'selected_configurable_option' => "",
                    'related_product' => "",
                    'item' => $item->getProductId(),
                    'form_key' => "",
                    'qty' => (int)$item->getQtyOrdered()
                ];
                $preparedProductData->setOptions($option);

                $options['product'] = $item->getProductId();
                $options['selected_configurable_option'] = "";
                $options['related_product'] = "";
                $options['item'] = $item->getProductId();
                $options['form_key'] = "";
                $options['qty'] = (int)$item->getQtyOrdered();

                $customerRequisitionListCollection = $this->requisitionListFactory->create()
                    ->getCollection()
                    ->addFieldToFilter('customer_id', $order->getCustomerId())
                    ->getFirstItem();
                if (!empty($requisitionListId = $customerRequisitionListCollection->getEntityId())) {
                    $this->requisitionListItemSaveHandler->saveItem($preparedProductData, $options, $itemId, $requisitionListId);
                }
            }
        }
    }
}
