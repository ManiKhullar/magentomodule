<?php
/**
 * Copyright © Echidna Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Echidna\ReorderPad\Observer;

use Echidna\ReorderPad\Api\ReorderPadInterface;
use Echidna\ReorderPad\Helper\Data;
use Echidna\ReorderPad\Publisher\AddConsumerQueue;
use Magento\Framework\Event\ObserverInterface;

class AfterSucessOrder implements ObserverInterface
{
    /**
     * @var AddConsumerQueue
     */
    protected $addConsumerQueue;
    /**
     * @var ReorderPadInterface
     */
    protected $reorderPad;
    /**
     * @var Data
     */
    protected $helper;

    public function __construct(
        AddConsumerQueue    $addConsumerQueue,
        ReorderPadInterface $reorderPad,
        Data                $helper
    )
    {
        $this->addConsumerQueue = $addConsumerQueue;
        $this->reorderPad = $reorderPad;
        $this->helper = $helper;
    }

    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        $isEnable = $this->helper->isEnabled();
        if ($isEnable) {
            $order = $observer->getEvent()->getOrder();
            if (!empty(is_array($order->getData())) && !$order->getCustomerIsGuest()) {
                $orderData = $this->reorderPad->setOrderId($order->getId());
                $this->addConsumerQueue->publish($orderData);
            }
        }
    }
}
