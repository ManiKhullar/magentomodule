<?php
/**
 * Created by PhpStorm.
 * User: Mani
 * Date: 29/12/21
 * Time: 5:20 PM
 */

namespace Echidna\InvoiceIntegration\Model\Data;

#use Echidna\InvoiceIntegration\Api\Data\InvoiceInformationInterface;
use Echidna\InvoiceIntegration\Api\Data\InvoiceItemDataInterface;
use Magento\Framework\DataObject;

class InvoiceInformation extends DataObject {
    /**
     * @return string|null
     */
    public function getPoNumber(): ?string
    {
        return $this->getData(self::PO_NUMBER);
    }

    /**
     * @param string $poNumber
     * @return InvoiceInformationInterface
     */
    public function setPoNumber(string $poNumber)    {
        return $this->setData(self::PO_NUMBER, $poNumber);
    }

    /**
     * @return string|null
     */
    public function getBuyerCode(): ?string
    {
        return $this->getData(self::BUYER_CODE);
    }

    /**
     * @param string $buyerCode
     * @return InvoiceInformationInterface
     */
    public function setBuyerCode(string $buyerCode)
    {
        return $this->setData(self::BUYER_CODE, $buyerCode);
    }

    /**
     * @return string|null
     */
    public function getDocumentNumber(): ?string
    {
        return $this->getData(self::DOCUMENT_NUMBER);
    }

    /**
     * @param string $documentNumber
     * @return InvoiceInformationInterface
     */
    public function setDocumentNumber(string $documentNumber)
    {
        return $this->setData(self::DOCUMENT_NUMBER, $documentNumber);
    }

    public function getDueDate(): ?string
    {
        return $this->getData(self::DUE_DATE);
    }

    /**
     * @param string $dueDate
     * @return InvoiceInformationInterface
     */
    public function setDueDate(string $dueDate)
    {
        return $this->setData(self::DUE_DATE, $dueDate);
    }

    /**
     * @return float|null
     */
    public function getDueAmount(): ?float
    {
        return $this->getData(self::DUE_AMOUNT);
    }

    /**
     * @param float $dueAmount
     * @return InvoiceInformationInterface
     */
    public function setDueAmount(float $dueAmount)
    {
        return $this->setData(self::DUE_AMOUNT, $dueAmount);
    }

    /**
     * @return int|null
     */
    public function getItemInternalId(): ?int
    {
        return $this->getData(self::ITEM_INTERNAL_ID);
    }

    /**
     * @param int $itemInternalId
     * @return InvoiceInformationInterface
     */
    public function setItemInternalId(int $itemInternalId)
    {
        return $this->setData(self::ITEM_INTERNAL_ID, $itemInternalId);
    }

    /**
     * @return int|null
     */
    public function getQty(): ?int
    {
        return $this->getData(self::QTY);
    }

    /**
     * @param int $qty
     * @return InvoiceInformationInterface
     */
    public function setQty(int $qty)
    {
        return $this->setData(self::QTY, $qty);
    }

    /**
     * @return float|null
     */
    public function getPrice(): ?float
    {
        return $this->getData(self::PRICE);
    }

    /**
     * @param float $price
     * @return InvoiceInformationInterface
     */
    public function setPrice(float $price)
    {
        return $this->setData(self::PRICE, $price);
    }

    /**
     * @return float|null
     */
    public function getRowTotal(): ?float
    {
        return $this->getData(self::ROW_TOTAL);
    }

    /**
     * @param float $rowTotal
     * @return InvoiceInformationInterface
     */
    public function setRowTotal(float $rowTotal)
    {
        return $this->setData(self::ROW_TOTAL, $rowTotal);
    }

    /**
     * @return float|null
     */
    public function getSubTotal(): ?float
    {
        return $this->getData(self::SUB_TOTAL);
    }

    /**
     * @param float $subTotal
     * @return InvoiceInformationInterface
     */
    public function setSubTotal(float $subTotal)
    {
        return $this->setData(self::SUB_TOTAL, $subTotal);
    }

    /**
     * @return float|null
     */
    public function getTaxTotal(): ?float
    {
        return $this->getData(self::TAX_TOTAL);
    }

    /**
     * @param float $taxTotal
     * @return InvoiceInformationInterface
     */
    public function setTaxTotal(float $taxTotal)
    {
        return $this->setData(self::TAX_TOTAL, $taxTotal);
    }

    public function getTotal(): ?float
    {
        return $this->getData(self::TOTAL);
    }

    /**
     * @param float $total
     * @return InvoiceInformationInterface
     */
    public function setTotal(float $total)
    {
        return $this->setData(self::TOTAL, $total);
    }

    /*public function getItems()    {
        return $this->getData(self::ITEMS);
    }

    public function setItems($items)    {
        return $this->setData(self::ITEMS, $items);
    }*/
}
