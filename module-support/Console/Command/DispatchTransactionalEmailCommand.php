<?php


namespace Altayer\Support\Console\Command;

use Altayer\Support\Helper\Data as Helper;
use Magento\Sales\Model\OrderFactory;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class DispatchTransactionalEmailCommand extends Command
{
    const SALES_ORDER_IDS = 'orders';
    const ORDER_EMAIL = 'order_email';
    const INVOICE_EMAIL = 'invoice_email';
    const CREDITMEMO_EMAIL = 'creditmemo_email';

    /** @var OrderFactory $order */
    protected $order;

    /** @var Helper $helper */
    protected $helper;

    /**
     * @param Helper $helper
     * @param OrderFactory $order
     * @param null $name
     */
    public function __construct(
        Helper $helper,
        OrderFactory $order,
        $name = null
    )
    {
        parent::__construct($name);
        $this->order = $order;
        $this->helper = $helper;
    }


    /**
     * {@inheritdoc}
     */
    protected function configure()
    {

        $options = [
            new InputOption(
                self::SALES_ORDER_IDS,
                null,
                InputOption::VALUE_REQUIRED,
                'Order Increment IDs (comma separated)'
            ),
            new InputOption(
                self::ORDER_EMAIL,
                null,
                InputOption::VALUE_OPTIONAL,
                'Send order email',
                false
            ),
            new InputOption(
                self::INVOICE_EMAIL,
                null,
                InputOption::VALUE_OPTIONAL,
                'Send invoice email',
                false
            ),
            new InputOption(
                self::CREDITMEMO_EMAIL,
                null,
                InputOption::VALUE_OPTIONAL,
                'Send credit memo email',
                false
            ),
        ];

        $this->setName('emails:dispatch');
        $this->setDescription('Dispatch transactional emails for given order id');
        $this->setDefinition($options);

        parent::configure();
    }

    /**
     * Method executed when cron runs in server
     * @param InputInterface $input
     * @param OutputInterface $output
     */
    public function execute(
        InputInterface $input,
        OutputInterface $output
    )
    {
        $orders = $input->getOption(self::SALES_ORDER_IDS);
        $orderEmail = $input->getOption(self::ORDER_EMAIL);
        $invoiceEmail = $input->getOption(self::INVOICE_EMAIL);
        $creditmemoEmail = $input->getOption(self::CREDITMEMO_EMAIL);

        if (empty($orders)) {
            return;
        }

        $ordersArray = explode(',', $orders);
        foreach ($ordersArray as $orderIncrementId) {
            $order = $this->order->create();
            $order->loadByIncrementId($orderIncrementId);
            if (!$order->getEntityId()) {
                $output->writeln("Order # $orderIncrementId not found.");
                continue;
            }

            $this->helper->sendTransactionalEmails($order, $orderEmail, $invoiceEmail, $creditmemoEmail);
        }
    }
}