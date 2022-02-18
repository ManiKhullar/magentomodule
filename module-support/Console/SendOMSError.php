<?php
/**
 * @author Ryazuddin
 * @package Altayer_Support
 * @date 16/09/2020
 * */

namespace Altayer\Support\Console;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Altayer\Support\Model\Helper as Helper;

/**
 * Class SendOMSError
 * @package Altayer\Support\Console
 */
class SendOMSError extends Command
{
    protected $helperr;

    protected function configure()
    {
        $this->setName('altayer:send:omserror');
        $this->setDescription('Send all the OMS error report to support team daily');
        parent::configure();
    }

    /**
     * SendReminder constructor.
     * @param Helper $helper
     * @param null $name
     */
    public function __construct(
        Helper $helper,
        $name = null
    )
    {
        parent::__construct($name);
        $this->helperr = $helper;
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return $this|int|null
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->helperr->sendOMSError($output);
        return $this;
    }

}
