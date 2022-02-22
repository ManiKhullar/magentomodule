<?php
/**
 * @author   Mani <kmanidev6@gmail.com>
 * @package Altayer_Support
 * @date 16/08/2020
 * */

namespace Altayer\Support\Console;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Altayer\Support\Model\AbandonedCart as Reminder;

/**
 * Class SendReminder
 * @package Altayer\Support\Console
 */
class SendReminder extends Command
{
    protected $reminder;

    protected function configure()
    {
        $this->setName('altayer:send:reminder');
        $this->setDescription('Send Reminder data to Salesforce team');
        parent::configure();
    }

    /**
     * SendReminder constructor.
     * @param Reminder $reminder
     * @param null $name
     */
    public function __construct(
        Reminder $reminder,
        $name = null
    )
    {
        parent::__construct($name);
        $this->reminder = $reminder;
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return $this|int|null
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->reminder->sendReminderData($output);
        return $this;
    }

}
