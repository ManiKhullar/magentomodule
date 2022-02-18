<?php
/**
 * Created by PhpStorm.
 * User: Nandisha N
 * Date: 29/12/21
 * Time: 5:21 PM
 */

namespace Echidna\InvoiceIntegration\Model;

use Echidna\InvoiceIntegration\Api\Data\ApiResponseDataInterfaceFactory;
use Echidna\InvoiceIntegration\Api\Data\ApiResponseDataInterface;
#use Echidna\InvoiceIntegration\Api\Data\InvoiceInformationInterface;
use Echidna\InvoiceIntegration\Api\InvoiceManagementInterface;
use Magento\Catalog\Model\ProductFactory;
use Magento\Framework\DB\Transaction;
use Magento\Framework\DB\TransactionFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Api\Data\InvoiceInterface;
use Magento\Sales\Api\Data\OrderInterfaceFactory;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Convert\Order;
use Magento\Sales\Model\Convert\OrderFactory;
use Magento\Sales\Model\Order\Email\Sender\InvoiceSender;
use Magento\Sales\Model\Order\Email\Sender\ShipmentSender;
use Magento\Sales\Model\Order\Invoice\ItemFactory;
use Magento\Sales\Model\Service\InvoiceService;

class InvoiceManagement implements InvoiceManagementInterface
{
    /** @var ApiResponseDataInterfaceFactory  */
    private  $apiResponseDataInterfaceFactory;

    /** @var OrderInterfaceFactory  */
    private $orderFactory;

    /** @var InvoiceInterface  */
    private $invoiceInterface;

    /** @var InvoiceService  */
    private $_invoiceService;

    /** @var Transaction  */
    private $_transaction;

    /** @var OrderRepositoryInterface  */
    private $_orderRepository;

    /** @var ItemFactory  */
    private $_invoiceItemFactory;

    /** @var ProductFactory  */
    private $productFactory;

    /** @var InvoiceSender  */
    private $invoiceSender;

    /** @var Order */
    private $orderConverter;

    /** @var \Magento\Framework\DB\TransactionFactory */
    private $transactionFactory;

    /** @var \Magento\Sales\Model\Order\Email\Sender\ShipmentSender */
    private $shipmentSender;
    private $invoiceModel;
    private $request;

    /**
     * InvoiceManagement constructor.
     * @param ApiResponseDataInterfaceFactory $apiResponseDataInterfaceFactory
     * @param OrderInterfaceFactory $orderFactory
     * @param InvoiceInterface $invoiceInterface
     * @param InvoiceService $invoiceService
     * @param Transaction $transaction
     * @param OrderRepositoryInterface $orderRepository
     * @param ItemFactory $invoiceItemFactory
     * @param ProductFactory $productFactory
     * @param InvoiceSender $invoiceSender
     * @param OrderFactory $convertOrderFactory
     * @param TransactionFactory $transactionFactory
     * @param ShipmentSender $shipmentSender
     */
    public function __construct(
        ApiResponseDataInterfaceFactory $apiResponseDataInterfaceFactory,
        OrderInterfaceFactory $orderFactory,
        InvoiceInterface $invoiceInterface,
        InvoiceService $invoiceService,
        Transaction $transaction,
        OrderRepositoryInterface $orderRepository,
        ItemFactory $invoiceItemFactory,
        ProductFactory $productFactory,
        InvoiceSender $invoiceSender,
        OrderFactory $convertOrderFactory,
        TransactionFactory $transactionFactory,
        \Magento\Framework\Webapi\Rest\Request $request,
        ShipmentSender $shipmentSender,
        \Magento\Sales\Model\Order\Invoice $invoiceModel
    ) {
        $this->apiResponseDataInterfaceFactory = $apiResponseDataInterfaceFactory;
        $this->orderFactory = $orderFactory;
        $this->invoiceInterface = $invoiceInterface;
        $this->_invoiceService = $invoiceService;
        $this->_transaction = $transaction;
        $this->_orderRepository = $orderRepository;
        $this->_invoiceItemFactory = $invoiceItemFactory;
        $this->productFactory = $productFactory;
        $this->invoiceSender = $invoiceSender;
        $this->orderConverter = $convertOrderFactory->create();
        $this->transactionFactory = $transactionFactory;
        $this->shipmentSender = $shipmentSender;
        $this->invoiceModel = $invoiceModel;
        $this->request = $request;
    }


    /**
     * @param InvoiceInformationInterface $invoiceData
     * @return ApiResponseDataInterface
     * @throws \Exception
     */
    public function createInvoice(): ApiResponseDataInterface
    {

        $requestDataArray = $this->getInvoiceData();
foreach($requestDataArray as $requestData) {
    print_r($requestData);exit;

    try {
        $poNumber = $requestData['po_number'];
        $items = $requestData['items'];
        $response = $this->apiResponseDataInterfaceFactory->create();
        $order = $this->orderFactory->create();
        $order->loadByIncrementId($poNumber);
        $orderRepository = $this->_orderRepository->get($order->getEntityId());
        $orderedProductSku = $this->getOrderedProductSku($orderRepository);
        $itemInvoice = [];
        $skuArray = $this->getProductSku($items);
        $productInfo = $this->getProductInfo($items);
        $orderQty = 0;
        $invoicedQty = 0;
        $item_id = 0;
        $itemInvoice = [];
        $updateItemInfo = [];
        $nonExistItem = [];
        foreach ($productInfo as $product) {
            foreach ($orderRepository->getItems() as $item) {
                if ($product['sku'] === $item->getSku()) {
                    $invoicedQty = $item->getQtyInvoiced();
                    $orderQty = $item->getQtyOrdered();
                    $item_id = $item->getItemId();
                    $qty = $orderQty - $invoicedQty;
                    if ($product['invoice_qty'] <= $qty) {
                        $itemInvoice[$item_id] = $product['invoice_qty'];

                    } else {
                        $remainInvoiceQty = $orderQty - $invoicedQty;
                        $itemInvoice[$item_id] = $remainInvoiceQty;
                        $updateItemInfo[] = ['item_id' => $item_id, 'qty' => $qty];

                    }
                } else {
                    if (!in_array($product['sku'], $orderedProductSku)) {

                        $nonExistItem[$product['sku']] = $product;

                    }
                }

            }

        }

        if ($itemInvoice && count($itemInvoice) > 0) {
            $invoice = $this->getCreateInvoice($orderRepository, $itemInvoice);
            if ($updateItemInfo && count($updateItemInfo) > 0) {
                $invoiceId = $invoice->getEntityId();
                $this->getUpdateInvoiceItemTable($invoiceId, $updateItemInfo);
            }
            if ($nonExistItem && count($nonExistItem) > 0) {
                $invoiceId = $invoice->getEntityId();
                $this->addNewItem($invoiceId, $nonExistItem, 1);

            }
        } else {
            $invoice = $this->createDummyInvoice($orderRepository, count($nonExistItem));
            $invoiceId = $invoice->getEntityId();
            $this->addNewItem($invoiceId, $nonExistItem, 0);

        }
        $this->invoiceSender->send($invoice);
        /*create Shipment */
        $this->getCreateShipment($orderRepository, $skuArray);
        $response->setStatus('true');
        $response->setMessage(__("Invoice has been generated"));
        $response->setStatus(true);

    } catch (\Exception $e) {
        $response = $this->apiResponseDataInterfaceFactory->create();
        $response->setMessage($e->getMessage());
        $response->setStatus(false);
    }
}
        return $response;
    }


    /**
     * @param $netSuiteId
     * @return mixed
     */
    public function getProductSku($itemArray)
    {
        $sku = [];
        foreach($itemArray as $item) {

            $product = $this->productFactory->create()->loadByAttribute('netsuite_internal_id', $item['item_internal_id']);
            if ($product) {
                $sku[] = $product->getData('sku');
            }
        }
        return $sku;
    }


    /**
     * @param $orderRepository
     * @param $itemInvoice
     * @return InvoiceInterface|\Magento\Sales\Model\Order\Invoice
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getCreateInvoice($orderRepository, $itemInvoice)
    {
        if($orderRepository->canInvoice())
        {
            $invoice = $this->_invoiceService->prepareInvoice($orderRepository,$itemInvoice);
            $invoice->register();
            $invoice->save();
            $invoice->getEntityId();
            $transactionSave = $this->_transaction->addObject($invoice)->addObject($invoice->getOrder());
            $transactionSave->save();

        }

        return $invoice;
    }

    /**
     * @param $parentId
     * @param $updateItem array
     * @throws \Exception
     */
    public function getUpdateInvoiceItemTable($parentId, $updateItem)
    {
        foreach($updateItem as $item) {
            $invoiceItemFactory = $this->_invoiceItemFactory->create();
            $invoiceItemFactory->load($parentId, 'parent_id');

            $entityId = $invoiceItemFactory->getEntityId();
            $invoiceItemFactory->load($item['item_id'],'order_item_id');
            $qty = $invoiceItemFactory->getQty(); // Invoiced qty
            $totalQty = $qty + $item['qty'];
            $price = $invoiceItemFactory->getBasePrice() * $totalQty;
            $invoiceItemFactory->setQty($totalQty);
            $invoiceItemFactory->setOrderItemId($item['item_id']);
            $invoiceItemFactory->setRowTotal($price);
            $invoiceItemFactory->setBaseRowTotal($price);
            $invoiceItemFactory->setBaseRowTotalInclTax($price);
            $invoiceItemFactory->setRowTotalInclTax($price);
            $invoiceItemFactory->save();

            /* load invoice and update invoice total*/
            $effectivePrice = $price - $invoiceItemFactory->getBasePrice()*$qty;
            $invoice = $this->invoiceModel->load($parentId);
            $subTotal = $invoice->getSubtotal()+$effectivePrice;
            $baseSubtotal = $invoice->getBaseSubtotal()+$effectivePrice;
            $subTotalInclTax = $invoice->getSubtotalInclTax()+$effectivePrice;
            $baseSubtotalInclTax = $invoice->getBaseSubtotalInclTax()+$effectivePrice;
            $grandTotal = $invoice->getGrandtotal()+$effectivePrice;
            $baseGrandTotal = $invoice->getGrandtotal()+$effectivePrice;
            // exit;
            $invoice->setSubtotal($subTotal);
            $invoice->setBaseSubtotal($baseSubtotal);
            $invoice->setSubtotalInclTax($subTotalInclTax);
            $invoice->setBaseSubtotalInclTax($baseSubtotalInclTax);
            //$invoice->setBaseSubtotal($baseSubtotal);
            $invoice->setGrandtotal($grandTotal);
            $invoice->setBaseGrandtotal($baseGrandTotal);
            $invoice->save();
        }
    }


    /**
     * @param $order
     * @param $sku
     * @return \Magento\Framework\Phrase|string
     */
    public function getCreateShipment($order, $sku)
    {
        $response = '';
        foreach ($order->getItems() as $item)
        {
            if(in_array($item->getSku(),$sku))
            {
                $invoicedQty = $item->getQtyInvoiced();
                $orderQty = $item->getQtyOrdered();
                if($invoicedQty >= $orderQty)
                {

                    if ($order->canShip()) {
                        try {
                            $order = $this->_orderRepository->get($order->getEntityId());
                            if (!$order->getId()) {
                                throw new \Magento\Framework\Exception\LocalizedException(__('The order no longer exists.'));
                            }
                            if ($order->canShip()) {
                                $shipment = $this->orderConverter->toShipment($order);
                                foreach ($order->getAllItems() as $orderItem) {

                                    if (!$orderItem->getQtyToShip() || $orderItem->getIsVirtual()) {
                                        continue;
                                    }
                                    $qtyShipped = $orderItem->getQtyToShip();
                                    $shipmentItem = $this->orderConverter->itemToShipmentItem($orderItem)->setQty($qtyShipped);
                                    $shipment->addItem($shipmentItem);
                                }
                                $shipment->getExtensionAttributes()->setSourceCode('cb_battery_network');
                                $shipment->register();
                                $shipment->getOrder()->setIsInProcess(true);
                                try{
                                    $transaction = $this->transactionFactory->create()->addObject($shipment)
                                        ->addObject($shipment->getOrder())
                                        ->save();
                                    $shipmentId = $shipment->getIncrementId();
                                }catch (\Exception $e){
                                    $response =  __('We can\'t generate shipment.');
                                }
                                $this->shipmentSender->send($shipment);
                                $response = 'shipment has been generated , shipment Id is :' . $shipmentId;
                            }
                        } catch (\Exception $e) {
                            $response =  $e->getMessage();
                        }
                    }
                }
            }
        }

        return $response;
    }

    /**
     * @param $itemArray
     * @return array
     */
    public function getProductInfo($itemArray)
    {
        $productInfo = [];
        foreach($itemArray as $item) {
            $product = $this->productFactory->create()->loadByAttribute('netsuite_internal_id', $item['item_internal_id']);
            if ($product) {
                $productInfo[] = ['sku'=>$product->getData('sku'),'name'=>$product->getData('name'),'id' => $product->getData('entity_id'),'price'=> $product->getData('price'),'netsuite_id'=>$item['item_internal_id'],'invoice_qty'=>$item['qty']];
            }
        }
        return $productInfo;
    }

    public function addNewItem($parentId,$productInfo,$flag=0){
        if($flag >0) {
            $invoice = $this->_invoiceItemFactory->create()->getCollection()->addFieldToFilter('parent_id', $parentId);
            $invoiceFirstItem = $invoice->getFirstItem();
            $orderItemId = $invoiceFirstItem->getOrderItemId();
            $invoiceItem = $this->_invoiceItemFactory->create();
        }else{
            $invoiceItem = $this->_invoiceItemFactory->create()->load($parentId, 'parent_id');
        }
        $invoice = $this->invoiceModel->load($parentId);
        try {
            foreach ($productInfo as $product) {
                $price = $product['price'] * $product['invoice_qty'];
                $invoiceItem->setQty($product['invoice_qty']);
                if ($flag > 0) {
                    $invoiceItem->setOrderItemId($orderItemId);
                }
                $invoiceItem->setProductId($product['id']);
                $invoiceItem->setSku($product['sku']);
                $invoiceItem->setName($product['name']);
                $invoiceItem->setNetsuitInternalId($product['netsuite_id']);
                $invoiceItem->setParentId($parentId);
                $invoiceItem->setRowTotal($price);
                $invoiceItem->setBasePrice($price);
                $invoiceItem->setPriceInclTax($price);
                $invoiceItem->setBasePriceInclTax($price);
                $invoiceItem->setPrice($price);
                $invoiceItem->setBaseRowTotal($price);
                $invoiceItem->setBaseRowTotalInclTax($price);
                $invoiceItem->setRowTotalInclTax($price);
                $invoiceItem->save();
                /* load invoice and update invoice total and send email*/

                $subTotal = $invoice->getSubtotal() + $price;
                $baseSubtotal = $invoice->getBaseSubtotal() + $price;
                $subTotalInclTax = $invoice->getSubtotalInclTax() + $price;
                $baseSubtotalInclTax = $invoice->getBaseSubtotalInclTax() + $price;
                $grandTotal = $invoice->getGrandtotal() + $price;
                $baseGrandTotal = $invoice->getGrandtotal() + $price;
                $invoice->setSubtotal($subTotal);
                $invoice->setBaseSubtotal($baseSubtotal);
                $invoice->setSubtotalInclTax($subTotalInclTax);
                $invoice->setBaseSubtotalInclTax($baseSubtotalInclTax);
                //$invoice->setBaseSubtotal($baseSubtotal);
                $invoice->setGrandtotal($grandTotal);
                $invoice->setBaseGrandtotal($baseGrandTotal);
                $invoice->save();


            }
        }catch(Exception $e){
            throw new \Magento\Framework\Exception\LocalizedException(__('The order no longer exists.'));

        }
    }
    public function createDummyInvoice($orderRepository,$count){
        $itemInvoice = [];
        $i = 1;
        foreach($orderRepository->getItems() as $item){
            //print_r($item->getItemId());

            $itemInvoice[$item->getItemId()] = 1;
            $i++;
            if($i >= $count)
                break;
        }

        try{
            //$itemInvoice[$firstItem] = 1;
            $invoice = $this->_invoiceService->prepareInvoice($orderRepository,$itemInvoice);
            $invoice->register();
            $invoice->save();

            $transactionSave =
                $this->_transaction
                    ->addObject($invoice)
                    ->addObject($invoice->getOrder());
            $transactionSave->save();
            return $invoice;
        }catch(Exception $e){
            return 0;
        }
    }

    public function getInvoiceData(){
        $params = $this->request->getBodyParams();
        return $params['invoiceData'];
    }

    /**
     * @param $orderRepository
     * @return array
     */
    public function getOrderedProductSku($orderRepository){
        $sku = [];
        foreach($orderRepository->getItems() as $item) {
            $sku[] = $item->getSku();
        }
        return $sku;
    }
}
