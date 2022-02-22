<?php
/**
 * Created by PhpStorm.
<<<<<<< HEAD
 * User: Mani
=======
 * User: mani
>>>>>>> 321a4a3e7674f0d6d825d0f9b1d736e40521fd51
 * Date: 20/12/21
 * Time: 11:36 PM
 */

namespace Echidna\InvoiceIntegration\Model\Data;

use Echidna\InvoiceIntegration\Api\Data\ApiResponseDataInterface;
use Magento\Framework\DataObject;

class ApiResponseData extends DataObject implements ApiResponseDataInterface
{

    public function getStatus()
    {
        return $this->getData(self::STATUS);
    }

    public function getMessage()
    {
        return $this->getData(self::MESSAGE);
    }

    public function setStatus($status)
    {
        return $this->setData(self::STATUS, $status);
    }

    public function setMessage($message)
    {
        return $this->setData(self::MESSAGE, $message);
    }
}
