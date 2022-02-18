<?php
/**
 * Generate pending invoice ...marking invoice to paid
 *
 * @package Altayer_Support
 * @author Amrendra Singh <amrendragr8@gmail.com>
 */


namespace Altayer\Support\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Altayer\Support\Model\CheckOrderInvoice;

class CheckInvoiceStatus extends Command
{
    CONST ORDER_ARRAY = 'increment_id';

    /**
     * @var Altayer\Support\Api\CheckOrderInvoiceInterface
     */
    protected $checkOrderInvoice;

    protected function configure()
    {
        $options = [
            new InputOption(
                self::ORDER_ARRAY,
                null,
                InputOption::VALUE_REQUIRED,
                'Enter Order Increment Id By Comma Seperated'
            )
        ];

        $this->setName('altayer:checkInvoiceStatus');
        $this->setDescription('Check and Update the order Invoice from pending to paid');
        $this->setDefinition($options);

        parent::configure();
    }

    public function __construct(
        CheckOrderInvoice $checkOrderInvoice,
        $name = null
    ) {
        parent::__construct($name);
        $this->checkOrderInvoice =$checkOrderInvoice;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if ($name = $input->getOption(self::ORDER_ARRAY)) {
            $this->checkOrderInvoice->checkInvoice($name, $output);
        } else {
            $output->writeln("please specify the order increment id with comma seperated");
        }

        return $this;
    }
}