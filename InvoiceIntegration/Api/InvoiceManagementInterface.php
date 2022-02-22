<?php
/**
 * Created by PhpStorm.
<<<<<<< HEAD
 * User: Mani
=======
 * User: mani
>>>>>>> 321a4a3e7674f0d6d825d0f9b1d736e40521fd51
 * Date: 29/12/21
 * Time: 5:10 PM
 */

namespace Echidna\InvoiceIntegration\Api;

use Echidna\InvoiceIntegration\Api\Data\ApiResponseDataInterface;
use Echidna\InvoiceIntegration\Api\Data\InvoiceInformationInterface;

interface InvoiceManagementInterface
{
    /**
     * @param InvoiceInformationInterface $invoiceData
     * @return ApiResponseDataInterface
     */
    public function createInvoice(): ApiResponseDataInterface;
}
