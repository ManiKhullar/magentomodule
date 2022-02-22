<?php
/**
 * Copyright © Echidna Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Echidna\ReorderPad\Publisher;

use Magento\Framework\MessageQueue\PublisherInterface;
use Echidna\ReorderPad\Api\ReorderPadInterface;

class AddConsumerQueue
{
    /**
     * @var string
     */
    const TOPIC_NAME = 'sales.order.success.queue';

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
     * @param ReorderPadInterface $orderData
     */
    public function publish($orderData)
    {
        $this->publisher->publish(self::TOPIC_NAME, $orderData);
    }
}
