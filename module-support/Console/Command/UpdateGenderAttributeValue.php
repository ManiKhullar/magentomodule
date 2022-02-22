<?php


namespace Altayer\Support\Console\Command;

use Altayer\Support\Model\UpdateGenderAttribute;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class UpdateGenderAttributeValue extends Command
{
    protected  $updateGenderAttribute;
    protected function configure()
    {
        $this->setName('altayer:updateGenderAttributeValue');
        $this->setDescription('Updating the gender mismatch data between the stores');

        parent::configure();
    }

    public function __construct(
        UpdateGenderAttribute $updateGenderAttribute,
        $name = null
    ) {
        parent::__construct($name);
        $this->updateGenderAttribute = $updateGenderAttribute;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $output->writeln('Updating the Gender Attribute Mismatch Data:');
        $this->updateGenderAttribute->updateGenderAttribute($output);
        $this->_runClearCache($output);
        $output->writeln('Finished');
    }

    /**
     * Clear cache
     * @return void
     */
    protected function _runClearCache($output)
    {
        $output->write('Cache refreshing...');
        shell_exec('php bin/magento cache:flush');
        $output->writeln(' Done');
    }
}