<?php
/**
 * Copyright © Echidna Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Echidna\ReorderPad\Model;

use Echidna\ReorderPad\Api\ReorderPadInterface;

class ReorderPad implements ReorderPadInterface
{
    /**
     * @var
     */
    protected $data;
    /**
     * @var
     */
    protected $orderId;

    /**
     * @param $data
     * @return $this|mixed
     */
    public function setData($data)
    {
        $this->data = $data;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getData()
    {
        return $this->data;
    }
    /**
     * @param $data
     * @return $this|mixed
     */
    public function setOrderId($orderId)
    {
        $this->orderId = $orderId;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getOrderId()
    {
        return $this->orderId;
    }
}
