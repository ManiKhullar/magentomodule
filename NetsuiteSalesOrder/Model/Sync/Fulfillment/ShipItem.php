<?php

namespace Echidna\NetsuiteSalesOrder\Model\Sync\Fulfillment;

use Echidna\Core\Model\Logger;
use Echidna\NetsuiteSalesOrder\Model\Config;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\InventoryInStorePickupSales\Model\NotifyOrdersAreReadyForPickup;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\Data\ShipmentCreationArgumentsExtensionInterfaceFactory;
use Magento\Sales\Api\Data\ShipmentCreationArgumentsInterfaceFactory;
use Magento\Sales\Api\Data\ShipmentItemCreationInterfaceFactory;
use Magento\Sales\Api\Data\ShipmentTrackCreationInterfaceFactory;
use Magento\Sales\Api\Exception\CouldNotShipExceptionInterface;
use Magento\Sales\Api\Exception\DocumentValidationExceptionInterface;
use Magento\Sales\Api\ShipmentRepositoryInterface;
use Magento\Sales\Api\ShipOrderInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\ResourceModel\Order as OrderResourceModel;
use Magento\Sales\Model\ResourceModel\Order\Shipment\CollectionFactory;
use Magento\Store\Model\ScopeInterface;
use NetSuite\Classes\ItemFulfillment;
use NetSuite\Classes\ItemFulfillmentItemList;
use NetSuite\Classes\Record;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;

/**
 * Sync the Netsuite Sales Order's internal ID to Magento's sales_order_table
 */
class ShipItem
{
    private int $scopeCode = 0;
    private array $shipments = [];
    private OrderResourceModel $orderResourceModel;
    private CollectionFactory $shipmentCollectionFactory;
    private OrderResourceModel\Shipment $shipmentResourceModel;
    private ShipmentRepositoryInterface $shipmentRepository;
    private NotifyOrdersAreReadyForPickup $notifyOrdersAreReadyForPickup;
    private ShipOrderInterface $shipOrder;
    private ShipmentItemCreationInterfaceFactory $shipmentItemCreationFactory;
    private ShipmentTrackCreationInterfaceFactory $shipmentTrackCreationFactory;
    private ShipmentCreationArgumentsInterfaceFactory $shipmentCreationArgumentsFactory;
    private ShipmentCreationArgumentsExtensionInterfaceFactory $shipmentCreationArgumentsExtensionFactory;
    private Config $config;
    private Logger $logger;
    private ProductCollectionFactory $productCollectionFactory;

    /**
     * @param OrderResourceModel $orderResourceModel
     * @param CollectionFactory $shipmentCollectionFactory
     * @param OrderResourceModel\Shipment $shipmentResourceModel
     * @param ShipmentRepositoryInterface $shipmentRepository
     * @param NotifyOrdersAreReadyForPickup $notifyOrdersAreReadyForPickup
     * @param ShipOrderInterface $shipOrder
     * @param ShipmentItemCreationInterfaceFactory $shipmentItemCreationFactory
     * @param ShipmentTrackCreationInterfaceFactory $shipmentTrackCreationFactory
     * @param ShipmentCreationArgumentsInterfaceFactory $shipmentCreationArgumentsFactory
     * @param ShipmentCreationArgumentsExtensionInterfaceFactory $shipmentCreationArgumentsExtensionFactory
     * @param Config $config
     * @param Logger $logger
     * @param ProductCollectionFactory $productCollectionFactory
     */
    public function __construct(
        OrderResourceModel                                 $orderResourceModel,
        CollectionFactory                                  $shipmentCollectionFactory,
        OrderResourceModel\Shipment                        $shipmentResourceModel,
        ShipmentRepositoryInterface                        $shipmentRepository,
        NotifyOrdersAreReadyForPickup                      $notifyOrdersAreReadyForPickup,
        ShipOrderInterface                                 $shipOrder,
        ShipmentItemCreationInterfaceFactory               $shipmentItemCreationFactory,
        ShipmentTrackCreationInterfaceFactory              $shipmentTrackCreationFactory,
        ShipmentCreationArgumentsInterfaceFactory          $shipmentCreationArgumentsFactory,
        ShipmentCreationArgumentsExtensionInterfaceFactory $shipmentCreationArgumentsExtensionFactory,
        Config                                             $config,
        Logger                                             $logger,
        ProductCollectionFactory                           $productCollectionFactory
    )
    {
        $this->orderResourceModel = $orderResourceModel;
        $this->shipmentCollectionFactory = $shipmentCollectionFactory;
        $this->shipmentResourceModel = $shipmentResourceModel;
        $this->shipmentRepository = $shipmentRepository;
        $this->notifyOrdersAreReadyForPickup = $notifyOrdersAreReadyForPickup;
        $this->shipOrder = $shipOrder;
        $this->shipmentItemCreationFactory = $shipmentItemCreationFactory;
        $this->shipmentTrackCreationFactory = $shipmentTrackCreationFactory;
        $this->shipmentCreationArgumentsFactory = $shipmentCreationArgumentsFactory;
        $this->shipmentCreationArgumentsExtensionFactory = $shipmentCreationArgumentsExtensionFactory;
        $this->config = $config;
        $this->logger = $logger;
        $this->productCollectionFactory = $productCollectionFactory;
    }

    /**
     * @param OrderInterface $order
     * @param ItemFulfillment $itemFulfillment
     * @throws AlreadyExistsException
     * @throws InputException
     * @throws NoSuchEntityException
     */
    public function process(OrderInterface $order, ItemFulfillment $itemFulfillment): void
    {
        $this->scopeCode = $order->getStoreId();
        if ($order->getShippingMethod() == "instore_pickup") {
            $this->markOrderAsReadyForPickup($order->getEntityId());
        } else {
            $processedShipments = $this->getShipmentInfo($order->getEntityId());
            if (!in_array($itemFulfillment->internalId, $processedShipments)) {
                try {
                    $this->shipOrder($order, $itemFulfillment);
                } catch (AlreadyExistsException $e) {
                    $this->logger->critical(__("Netsuite item fulfillment was not synced for the Magento order: %1", $order->getIncrementId()));
                    if ($this->config->isLogEnabled(ScopeInterface::SCOPE_WEBSITES, $this->scopeCode)) {
                        $this->logger->debug($e->getMessage());
                        $this->logger->debug($e->getTraceAsString());
                    }
                }
            }
        }
    }

    /**
     * @param $orderId
     * @return array
     */
    private function getShipmentInfo($orderId): array
    {
        if (!isset($this->shipments[$orderId])) {
            $collection = $this->shipmentCollectionFactory->create();
            $collection->addFieldToFilter('order_id', $orderId);
            $shipments = [];
            foreach ($collection as $shipment) {
                $shipments[] = $shipment->getData('netsuite_transaction_id');
            }
            $this->shipments[$orderId] = $shipments;
        }
        return $this->shipments[$orderId];
    }

    /**
     * Mark the order as ready for pickup
     * @param int $orderId
     */
    private function markOrderAsReadyForPickup(int $orderId)
    {
        $this->notifyOrdersAreReadyForPickup->execute([$orderId]);
    }

    /**
     * Ship the order based on the Netsuite ItemFulfillment data
     * @param Order|OrderInterface $order
     * @param ItemFulfillment|Record $fulfillmentData
     * @throws DocumentValidationExceptionInterface
     * @throws CouldNotShipExceptionInterface
     * @throws AlreadyExistsException
     */
    public function shipOrder(Order $order, ItemFulfillment $fulfillmentData)
    {
        $items = $this->getItemsToShip($fulfillmentData->itemList, $order);
        $trackingData = $this->getTrackingData($fulfillmentData);
        $arguments = $this->shipmentCreationArgumentsFactory->create();
        $extensionAttributes = $this->shipmentCreationArgumentsExtensionFactory->create();
        $extensionAttributes->setSourceCode($this->getShipmentSource($fulfillmentData->itemList));
        $arguments->setExtensionAttributes($extensionAttributes);
        $shipmentId = $this->shipOrder->execute(
            $order->getEntityId(),
            $items,
            true,
            false,
            null,
            $trackingData,
            [],
            $arguments
        );
        // if shipment is created, update the itemFulfillment internalId
        if ($shipmentId) {
            $this->updateShipmentInternalId($shipmentId, $fulfillmentData->internalId);
        }
    }


// TODO: Add condition when partial shipment happens for those applications.
    private function getItemsToShip(ItemFulfillmentItemList $itemList, Order $order): array
    {
        $items = [];
        foreach ($itemList->item as $item) {
            foreach ($order->getItems() as $orderItem) {
                if ($orderItem->getData('netsuite_internal_id') === $item->item->internalId) {
                    $items[] = $this->shipmentItemCreationFactory->create()
                        ->setOrderItemId($orderItem->getId())
                        ->setQty($item->quantity);
                }
            }
        }
        return $items;
    }

    /**
     * @param ItemFulfillment $fulfillment
     * @return array
     */
    private function getTrackingData(ItemFulfillment $fulfillment): array
    {
        $trackingData = [];
        foreach ($fulfillment->packageFedExList->packageFedEx as $item) {
            $trackingData[] = $this->shipmentTrackCreationFactory->create()->setCarrierCode($fulfillment->shipMethod->name)
                ->setTitle($fulfillment->shipMethod->name)
                ->setTrackNumber($item->packageTrackingNumberFedEx);
        }
        return $trackingData;
    }

    /**
     * @param ItemFulfillmentItemList $itemList
     * @return string
     */
    private function getShipmentSource(ItemFulfillmentItemList $itemList): string
    {
        $location = "";
        foreach ($itemList->item as $item) {
            if (!empty($item->location->name)) {
                $location = $item->location->name;
                break;
            }
        }
        return $this->config->getSourceCodeByNetsuiteLocation($location, ScopeInterface::SCOPE_WEBSITES, $this->scopeCode);
    }

    /**
     * @param int $shipmentId
     * @param string|int internalId
     * @throws AlreadyExistsException
     */
    private function updateShipmentInternalId(int $shipmentId, $internalId): void
    {
        /** @var Order\Shipment $shipment */
        $shipment = $this->shipmentRepository->create();
        $this->shipmentResourceModel->load($shipment, $shipmentId);
        $shipment->setData('netsuite_transaction_id', $internalId);
        $this->shipmentResourceModel->save($shipment);
    }
}
