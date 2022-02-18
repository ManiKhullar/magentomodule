<?php

namespace Echidna\NetsuiteSalesOrder\Model\Sync\Order;

use Echidna\Core\Model\Logger;
use Echidna\NetsuiteSalesOrder\Model\Config;
use Exception;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\ResourceModel\Order as OrderResourceModel;
use Magento\Store\Model\ScopeInterface;

/**
 * Update Sales Order data
 */
class UpdateOrder
{
    private OrderResourceModel $orderResourceModel;
    private Config $config;
    private Logger $logger;

    /**
     * @param OrderResourceModel $orderResourceModel
     * @param Config $config
     * @param Logger $logger
     */
    public function __construct(OrderResourceModel $orderResourceModel, Config $config, Logger $logger)
    {
        $this->orderResourceModel = $orderResourceModel;
        $this->config = $config;
        $this->logger = $logger;
    }

    /**
     * @param OrderInterface|Order $order
     * @param int $netsuiteInternalId
     */
    public function updateNetsuiteDetail(OrderInterface $order, int $netsuiteInternalId): void
    {
        try {
            $order->setData('netsuit_order_id', $netsuiteInternalId);
            $this->orderResourceModel->save($order);
        } catch (AlreadyExistsException | Exception $e) {
            $this->logger->critical(__("Netsuite order internal id was not saved for the order increment_id: %1", $order->getIncrementId()));
            if ($this->config->isLogEnabled(ScopeInterface::SCOPE_STORES, $order->getStoreId())) {
                $this->logger->debug($e->getMessage());
                $this->logger->debug($e->getTraceAsString());
            }
        }
    }
}
