<?php
/**
 * Created by PhpStorm.
 * User: Mani
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
