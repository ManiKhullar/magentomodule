<?php


namespace Altayer\Support\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Sales\Model\Order\Email\Sender\CreditmemoSender;
use Magento\Sales\Model\Order\Email\Sender\InvoiceSender;
use Magento\Sales\Model\Order\Email\Sender\OrderSender;
use Magento\Sales\Model\OrderFactory;

class Data extends AbstractHelper
{
    /** @var LoggerInterface */
    protected $logger;

    /** @var OrderFactory $order */
    protected $order;

    /** @var OrderSender $orderSender */
    protected $orderSender;

    /** @var InvoiceSender $invoiceSender */
    protected $invoiceSender;

    /** @var CreditmemoSender $creditmemoSender */
    protected $creditmemoSender;

    const XML_AWS_ENABLE_DEV_MODE = 'altayer_order_monitor/vpnimportdata/enable_dev_mode';
    const XML_AWS_BUCKET_NAME = 'altayer_order_monitor/vpnimportdata/bucket_name';
    const XML_AWS_PREFIX = 'altayer_order_monitor/vpnimportdata/prefix';
    const XML_AWS_REGION = 'altayer_order_monitor/vpnimportdata/region';
    const XML_AWS_FILE_LOCATION = 'altayer_order_monitor/vpnimportdata/file_download_location';

    public function __construct(
        OrderSender $orderSender,
        CreditmemoSender $creditmemoSender,
        InvoiceSender $invoiceSender,
        OrderFactory $order,
        Context $context
    )
    {
        parent::__construct($context);
        $this->logger = $context->getLogger();
        $this->order = $order;
        $this->orderSender = $orderSender;
        $this->invoiceSender = $invoiceSender;
        $this->creditmemoSender = $creditmemoSender;
    }

    /**
     * @param \Magento\Sales\Model\Order $order
     * @param bool $sendOrderEmail
     * @param bool $sendInvoiceEmail
     * @param bool $sendCreditmemoEmail
     */
    public function sendTransactionalEmails($order, $sendOrderEmail = false, $sendInvoiceEmail = false, $sendCreditmemoEmail = false)
    {
        if (!$order->getEntityId()) {
            return;
        }
        if ($sendOrderEmail) {
            $this->sendOrderEmail($order);
        }
        if ($sendInvoiceEmail) {
            $this->sendInvoiceEmail($order);
        }
        if ($sendCreditmemoEmail) {
            $this->sendCreditMemoEmail($order);
        }
    }

    /**
     * @param \Magento\Sales\Model\Order $order
     */
    protected function sendOrderEmail($order)
    {
        $order->setEmailSent(false);
        $this->orderSender->send($order);
    }

    /**
     * @param \Magento\Sales\Model\Order $order
     */
    protected function sendInvoiceEmail($order)
    {
        if (!$order->hasInvoices()) {
            return;
        }
        /** @var \Magento\Sales\Model\Order\Invoice $invoice */
        foreach ($order->getInvoiceCollection() as $invoice) {
            $this->logger->info('Sending email for invoice', [
                'order' => $order->getIncrementId(),
                'invoice' => $invoice->getIncrementId()
            ]);
            $invoice->setEmailSent(false);
            $this->invoiceSender->send($invoice, true);
            break;
        }
    }

    /**
     * @param \Magento\Sales\Model\Order $order
     */
    protected function sendCreditMemoEmail($order)
    {
        if (!$order->hasCreditmemos()) {
            return;
        }
        /** @var \Magento\Sales\Model\Order\Creditmemo $creditmemo */
        foreach ($order->getCreditmemosCollection() as $creditmemo) {
            $this->logger->info('Sending email for creditmemo', [
                'order' => $order->getIncrementId(),
                'creditmemo' => $creditmemo->getIncrementId()
            ]);
            $creditmemo->setEmailSent(false);
            $this->creditmemoSender->send($creditmemo, true);
        }
    }

    /**
     * @return mixed
     */
    public function isDevModeEnable()
    {
        return $this->scopeConfig->getValue(self::XML_AWS_ENABLE_DEV_MODE);
    }

    /**
     * @return mixed
     */
    public function getBucketName()
    {
        return $this->scopeConfig->getValue(self::XML_AWS_BUCKET_NAME);
    }

    /**
     * @return mixed
     */
    public function getPrefix()
    {
        return $this->scopeConfig->getValue(self::XML_AWS_PREFIX);
    }

    /**
     * @return mixed
     */
    public function getDownloadFileLocation()
    {
        return $this->scopeConfig->getValue(self::XML_AWS_FILE_LOCATION);
    }
}
