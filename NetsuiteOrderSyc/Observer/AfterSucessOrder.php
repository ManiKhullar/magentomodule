<?php
/**
 * Copyright © Echidna Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Echidna\NetsuiteOrderSyc\Observer;

use Echidna\NetsuiteOrderSyc\Api\OrderSycInterface;
use Echidna\NetsuiteOrderSyc\Publisher\AddConsumerQueue;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

class AfterSucessOrder implements ObserverInterface
{
    /**
     * @var AddConsumerQueue
     */
    protected AddConsumerQueue $addConsumerQueue;
    /**
     * @var OrderSycInterface
     */
    protected OrderSycInterface $orderSyc;

    /**
     * @param AddConsumerQueue $addConsumerQueue
     * @param OrderSycInterface $orderSyc
     */
    public function __construct(
        AddConsumerQueue  $addConsumerQueue,
        OrderSycInterface $orderSyc
    )
    {
        $this->addConsumerQueue = $addConsumerQueue;
        $this->orderSyc = $orderSyc;
    }

    /**
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer)
    {
        $order = $observer->getEvent()->getOrder();
        if (!empty(is_array($order->getData()))) {
            $orderData = $this->orderSyc->setOrderId($order->getId());
            $this->addConsumerQueue->publish($orderData);
        }
    }
}
