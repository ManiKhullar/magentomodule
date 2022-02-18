<?php

namespace Echidna\NetsuiteSalesOrder\Cron;

use Echidna\Core\Model\Logger;
use Echidna\NetsuiteSalesOrder\Model\Sync\Fulfillment\ShipItem;
use Echidna\NetsuiteSalesOrder\Model\ScheduleHandler;
use Echidna\NetsuiteSalesOrder\Model\Sync\Fulfillment\DataProvider;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\NoSuchEntityException;
use NetSuite\Classes\ItemFulfillment;

/**
 * Sync the Netsuite Sales Order's internal ID to Magento's sales_order_table
 */
class FulfillmentSyncNetsuite
{
    const SYNC_FLAG = 'netsuite_fulfillment_sync.flag';
    private ShipItem $orderFulfillment;
    private ScheduleHandler $scheduleHandler;
    private DataProvider $dataProvider;
    private Logger $logger;

    /**
     * @param DataProvider $dataProvider
     * @param ShipItem $orderFulfillment
     * @param Logger $logger
     * @param ScheduleHandler $scheduleHandler
     */
    public function __construct(
        DataProvider    $dataProvider,
        ScheduleHandler $scheduleHandler,
        Logger          $logger,
        ShipItem        $orderFulfillment
    )
    {
        $this->dataProvider = $dataProvider;
        $this->scheduleHandler = $scheduleHandler;
        $this->orderFulfillment = $orderFulfillment;
        $this->logger = $logger;
    }

    /**
     * Process the shipment for the orders in Magento based on the itemfulfillment in NetSuite
     * @throws FileSystemException
     */
    public function execute()
    {
        $canProceed = $this->scheduleHandler->canProceedWithSchedule(self::SYNC_FLAG);
        if (!$canProceed) {
            return;
        }
        list($searchValues, $orders) = $this->dataProvider->prepareSearchValues();
        $storeId = 0; // to load global scope for netsuite connector api
        $response = $this->dataProvider->search($searchValues, $storeId);
        if ($response !== null && $response->totalRecords > 0) {
            /** @var ItemFulfillment $record */
            foreach ($response->recordList->record as $record) {
                $order = $orders[$record->createdFrom->internalId];
                try {
                    $this->orderFulfillment->process($order, $record);
                } catch (AlreadyExistsException | InputException | NoSuchEntityException $e) {
                    $this->logger->critical(__("Netsuite fulfillment was not processed for the order increment_id: %1", $storeId));
                    $this->logger->debug($e->getMessage());
                    $this->logger->debug($e->getTraceAsString());
                }
            }
        }
        $this->scheduleHandler->cleanFlag(self::SYNC_FLAG);
    }
}
