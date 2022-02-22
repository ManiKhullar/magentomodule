<?php
/**
 * Copyright © Echidna Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Echidna\NetsuiteOrderSyc\Publisher;

use Magento\Framework\MessageQueue\PublisherInterface;
use Echidna\NetsuiteOrderSyc\Api\OrderSycInterface;

class AddConsumerQueue
{
    /**
     * @var string
     */
    const TOPIC_NAME = 'netsuite.order.sysc';

    /**
     * @var PublisherInterface
     */
    protected $publisher;

    /**
     * @param PublisherInterface $publisher
     */
    public function __construct(
        PublisherInterface $publisher
    )
    {
        $this->publisher = $publisher;
    }

    /**
     * @param OrderSycInterface $orderData
     */
    public function publish($orderData)
    {
        $this->publisher->publish(self::TOPIC_NAME, $orderData);
    }
}
