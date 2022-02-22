<?php
/**
 * @category   Altayer
 * @package    Altayer_Support
 * @author   Mani <kmanidev6@gmail.com>
*/

namespace Altayer\Support\Console;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;
use Altayer\Support\Api\ProductUpdateInterface;

class UpdateProduct extends Command
{
    const PRODUCT_ARRAY = 'sku';

    private $_processProducts;

    protected function configure()
    {
        $options = [
            new InputOption(
                self::PRODUCT_ARRAY,
                null,
                InputOption::VALUE_REQUIRED,
                'Name'
            )
        ];

        $this->setName('altayer:updateProductData');
        $this->setDescription('Update Product using command line');
        $this->setDefinition($options);
       
        parent::configure();
    }

    public function __construct(
        ProductUpdateInterface $processProducts,
        $name = null
    ) {
        parent::__construct($name);
        $this->_processProducts =$processProducts;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if ($name = $input->getOption(self::PRODUCT_ARRAY)) {
            $this->_processProducts->updateProductData($name); 
        } else {
            $output->writeln("please specify the sku names with comma seperated");
        }

        return $this;
    }
}
