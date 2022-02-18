<?php


namespace Altayer\Support\Model;

class CheckOrderInvoice
{
    /**
     * @var  Symfony\Component\Console\Input\InputInterface
     */
    protected $input;

    /**
     * @var  Symfony\Component\Console\Input\OutputInterface
     */
    protected $output;

    /**
     * @var \Magento\Sales\Api\InvoiceManagementInterface
     */
    protected $invoiceManagement;

    /**
     * @var \Magento\Sales\Api\InvoiceRepositoryInterface
     */
    protected $invoiceRepository;

    /**
     * @var \Magento\Sales\Model\OrderFactory
     */
    protected $order;

    public function __construct(
        \Psr\Log\LoggerInterface $logger,
        \Magento\Sales\Model\OrderFactory $order,
        \Magento\Sales\Api\InvoiceManagementInterface $invoiceManagement,
        \Magento\Sales\Api\InvoiceRepositoryInterface $invoiceRepository
    ) {
        $this->_logger = $logger;
        $this->invoiceRepository = $invoiceRepository;
        $this->invoiceManagement = $invoiceManagement;
        $this->order = $order;
    }

    public function checkInvoice($incrementIds, $output){
        $incrementIdsArray = explode(',', $incrementIds);
        foreach ($incrementIdsArray as $incrementId){
            $output->writeln("Checking the Order Invoice Status of :: ". $incrementId);
            $orderDetails = $this->order->create()->loadByIncrementId($incrementId);
            // Checking the Order Status and Payment Method.
            if ($orderDetails->getStatus() !== \Magento\Sales\Model\Order::STATE_COMPLETE
                || $orderDetails->getPayment()->getMethod() !== \Magento\OfflinePayments\Model\Cashondelivery::PAYMENT_METHOD_CASHONDELIVERY_CODE){
                $output->writeln("Order is not completed yet or order is not COD order so skipping it");
                continue;
            }

            // Getting the Invoice Collection from the order
            $invoiceCollection = $orderDetails->getInvoiceCollection();
            foreach ($invoiceCollection as $invoice){
                if ($invoice->getState() == \Magento\Sales\Model\Order\Invoice::STATE_PAID){
                    $output->writeln("Invoice is already paid so skipping it");
                    continue;
                }
                // If Invoice is not paid capturing it.
                $invoiceDetails = $this->invoiceRepository->get($invoice->getId());
                $this->invoiceManagement->setCapture($invoiceDetails->getEntityId());
                $invoiceDetails->setRequestedCaptureCase(\Magento\Sales\Model\Order\Invoice::CAPTURE_OFFLINE)
                    ->setIsPaid(false)
                    ->capture();
                $invoiceDetails->save();
                $this->_logger->debug("INVOICE STATUS :: Invoice is made from pending to paid for order :: ". $incrementId);
                // Order Total Paid is not updating only by capturing the Invoice.
                if ($orderDetails->getTotalPaid() < $orderDetails->getGrandTotal()){
                    $totalPaid = $orderDetails->getTotalPaid()
                        + $invoiceDetails->getGrandTotal()
                        - $orderDetails->getTotalCanceled();
                    $baseTotalPaid = $orderDetails->getBaseTotalPaid()
                        + $invoiceDetails->getBaseGrandTotal()
                        - $orderDetails->getBaseTotalCanceled();
                    if ($totalPaid < 0) {
                        $totalPaid = 0;
                    }
                    if ($baseTotalPaid < 0) {
                        $baseTotalPaid = 0;
                    }
                    $orderDetails->setTotalPaid($totalPaid)
                        ->setBaseTotalPaid($baseTotalPaid);
                    $orderDetails->save();
                    $this->_logger->debug("INVOICE STATUS :: Order Total is updated for order :: ".$incrementId);
                }
                $output->writeln("Invoice is mark from pending to paid and updated the order total paid for this order");
            }
        }
    }
}