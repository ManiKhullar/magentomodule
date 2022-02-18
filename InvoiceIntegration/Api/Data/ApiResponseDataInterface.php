<?php
/**
 * Created by PhpStorm.
 * User: Nandisha N
 * Date: 20/12/21
 * Time: 10:22 PM
 */

namespace Echidna\InvoiceIntegration\Api\Data;

interface ApiResponseDataInterface
{
    const STATUS = 'status';
    const MESSAGE = 'message';

    /**
     * @return mixed
     */
    public function getStatus();

    /**
     * @return mixed
     */
    public function getMessage();

    /**
     * @param $status
     * @return mixed
     */
    public function setStatus($status);

    /**
     * @param $message
     * @return mixed
     */
    public function setMessage($message);

}
