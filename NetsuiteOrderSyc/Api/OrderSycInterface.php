<?php
/**
 * Copyright © Echidna Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Echidna\NetsuiteOrderSyc\Api;


interface OrderSycInterface
{
    /**
     * @return mixed
     */
    public function getData();

    /**
     * @return mixed
     */
    public function getOrderId();

    /**
     * @param $data
     * @return mixed
     */
    public function setData($data);

    /**
     * @param $orderId
     * @return mixed
     */
    public function setOrderId($orderId);
}
