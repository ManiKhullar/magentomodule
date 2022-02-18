<?php

namespace Echidna\NetsuiteSalesOrder\Cron;

use Echidna\NetsuiteSalesOrder\Model\ScheduleHandler;
use Echidna\NetsuiteSalesOrder\Model\Sync\Order\DataProvider;
use Echidna\NetsuiteSalesOrder\Model\Sync\Order\UpdateOrder;
use Magento\Framework\Exception\FileSystemException;
use NetSuite\Classes\SalesOrder;

/**
 * Sync the Netsuite Sales Order's internal ID to Magento's sales_order_table
 */
class OrderSyncNetsuite
{
    const SYNC_FLAG = 'netsuite_order_sync.flag';
    private ScheduleHandler $scheduleHandler;
    private DataProvider $dataProvider;
    private UpdateOrder $updateOrder;

    /**
     * @param UpdateOrder $updateOrder
     * @param DataProvider $dataProvider
     * @param ScheduleHandler $scheduleHandler
     */
    public function __construct(
        UpdateOrder     $updateOrder,
        DataProvider    $dataProvider,
        ScheduleHandler $scheduleHandler
    )
    {
        $this->updateOrder = $updateOrder;
        $this->dataProvider = $dataProvider;
        $this->scheduleHandler = $scheduleHandler;
    }

    /**
     * Sync the Netsuite Order Internal ID into Magento sales_order table
     * @throws FileSystemException
     */
    public function execute()
    {
		$canProceed = $this->scheduleHandler->canProceedWithSchedule(self::SYNC_FLAG);
        if (!$canProceed) {
            return;
        }
        $orderCollection = $this->dataProvider->getOrderCollection();
        /* prepare array for order search*/
        foreach ($orderCollection as $order) {
            $response = $this->dataProvider->search($order);
		    /** @var SalesOrder $record */
            if(isset($response->recordList)){

                foreach ($response->recordList->record as $record) {
                    $this->updateOrder->updateNetsuiteDetail($order, $record->internalId);
                }
            }
        }
        $this->scheduleHandler->cleanFlag(self::SYNC_FLAG);
        
    }
}
