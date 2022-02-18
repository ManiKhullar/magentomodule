<?php
/**
 * @author Amrendra Singh <amrendragr8@gmail.com>
 * @package Altayer_Support
 * */

namespace Altayer\Support\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Altayer\Support\Model\MarketingCampaign as Campaign;

class MarketingCampaign extends Command
{
    protected $marketingCampaign;

    protected function configure()
    {
        $this->setName('altayer:marketingCampaign');
        $this->setDescription('Generate the Marketing Campaign Data');
        parent::configure();
    }

    public function __construct(
        Campaign $marketingCampaign,
        $name = null
    ) {
        parent::__construct($name);
        $this->marketingCampaign = $marketingCampaign;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->marketingCampaign->getCampiagnData($output);
        return $this;
    }
}