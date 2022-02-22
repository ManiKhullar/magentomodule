<?php
/**
 * OrderExportFromIds.php
 * @package   Altayer\Support\Console
 * @author    Mani <kmanidev6@gmail.com>
 */

namespace Altayer\Support\Console;

use Altayer\Sales\Model\Utility;
use Altayer\Support\Model\Helper as Helper;
use Magento\Sales\Model\OrderFactory;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory;
use Magento\Framework\File\Csv;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\App\Area;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\ResourceConnection;
use Psr\Log\LoggerInterface as PsrLogger;
use Magento\Framework\Filesystem;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Filesystem\Directory\WriteInterface;
use Magento\Framework\ObjectManagerInterface;
use ZipArchive;


class OrderExportFromIds extends Command
{
    const NAME = 'sales:order:OrderExportFromIds';

    const INTERVAL = 'altayer_order_monitor/order_export/ranges';

    const ORDER_IDS = 'altayer_order_monitor/order_export/order_ids';

    const XML_PATH_BRAND_NAME = 'general/store_information/name';



    /**
     * Utility
     *
     * @var Utility
     */
    protected $utility;

    /**
     * @var OrderFactory
     */
    protected $orderFactory;

    /**
     * @var CollectionFactory
     */
    protected $collectionFactory;

    /**
     * @var $_logger
     */
    protected $_logger;


    /**
     * OrderEciReportCommand constructor.
     * @param Utility $utility
     * @param OrderFactory $orderFactory
     * @param CollectionFactory $collectionFactory
     * @param Csv $csv
     * @param DirectoryList $directoryList
     * @param ResourceConnection $resource
     * @param PsrLogger $logger
     * @param Helper $helper
     * @param null $name
     */
    public function __construct(
        Utility $utility,
        OrderFactory $orderFactory,
        CollectionFactory $collectionFactory,
        Csv $csv,
        DirectoryList $directoryList,
        ResourceConnection $resource,
        PsrLogger $logger,
        Helper $helper,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Altayer\Oms\Model\OrderManagement $orderManagement,
        Filesystem $filesystem,
        ObjectManagerInterface $objectManager,
        $name = null
    )
    {
        parent::__construct($name);
        $this->csv = $csv;
        $this->directoryList = $directoryList;
        $this->utility = $utility;
        $this->orderFactory = $orderFactory;
        $this->collectionFactory = $collectionFactory;
        $this->_logger = $logger;
        $this->helper = $helper;
        $this->scopeConfig = $scopeConfig;
        $this->orderManagement = $orderManagement;
        $this->_objectManager = $objectManager;
        $this->filesystem = $filesystem;
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this->setName(self::NAME);
        $this->setDescription('Order Export From Ids');
        parent::configure();
    }

    /**
     * export report orders
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $intervalOrderIds = $this->scopeConfig->getValue(self::INTERVAL);
        $OrderIds = $this->scopeConfig->getValue(self::ORDER_IDS);
        $OrderIdsArray = explode(",", $OrderIds);
        $encodedArrayValue = $this->getConfigValues();
        if(!is_null($OrderIds) && count($OrderIdsArray))
        {
            $this->exportOrderFileOrderIds($output,$OrderIdsArray);
        }else
        {
            if(($encodedArrayValue && is_array($encodedArrayValue)))
            {
                $this->exportOrderFile($encodedArrayValue,$output);
            }
        }

    }


    /**
     * @param $intervalOrderIds
     * @param $output
     */
    public function exportOrderFile($intervalOrderIds, $output)
    {
        try{
            $storeScope = \Magento\Store\Model\ScopeInterface::SCOPE_STORE;
            $brand = $this->scopeConfig->getValue(self::XML_PATH_BRAND_NAME, $storeScope);
            $varDirectory = $this->filesystem->getDirectoryWrite(
                DirectoryList::VAR_DIR
            );
            $fileName = $brand.'OrderExportNewNew';
            $fileNewName = $fileName;
            $date = (new \DateTime())->format('Y-m-d H:i:s');
            $fileNameCustom = $brand.'OrderExportItem'.$date.'txt';
            $path = $this->directoryList->getPath('var') . DIRECTORY_SEPARATOR . $fileName . "{$date}.txt";
            $orderfile = fopen($path, "w+");
            $progressBar = new \Symfony\Component\Console\Helper\ProgressBar($output, count($intervalOrderIds));
            $progressBar->setFormat(
                '<info>%message%</info> %current%/%max% [%bar%] %percent:3s%% %elapsed% %memory:6s%'
            );

            $progressBar->start();
            $progressBar->setMessage(str_pad('Getting Payload ...', 15, ' ', STR_PAD_RIGHT));
            $progressBar->display();
            foreach ($intervalOrderIds as $orderItem)
            {
                $order = $this->orderFactory->create();
                $order = $order->load($orderItem, 'entity_id');
                if($order->getId())
                {
                    try{
                        try {
                            $output->writeln(PHP_EOL);
                            $progressBar->setMessage(str_pad($order->getIncrementId() . ' ::Sending Create Payload...', 15, ' ', STR_PAD_RIGHT));
                            $output->writeln(PHP_EOL);
                            $progressBar->display();
                            $payload = $this->orderManagement->getCreateOrderPayload($order);
                            $payload= json_encode($payload,true). PHP_EOL.PHP_EOL.PHP_EOL;
                            $output->writeln(PHP_EOL);
                            $output->writeln("Order Create Payload Send::".$order->getIncrementId().PHP_EOL);
                            $output->writeln(PHP_EOL);
                            $output->writeln(PHP_EOL);
                            $progressBar->advance();
                        }catch(\Exception $e)
                        {
                            $this->_logger->critical("Order Create Payload ::  Error :" . $e->getMessage());
                            $payload= ''. PHP_EOL.PHP_EOL.PHP_EOL;
                            $output->writeln("Order Create Payload Error::".$order->getIncrementId().PHP_EOL);
                            $output->writeln(PHP_EOL);
                            continue;
                        }
                        fwrite($orderfile, $payload);
                    }catch(\Exception $e)
                    {
                        $this->_logger->critical("Order Create Payload Error ::  Error :" . $e->getMessage());
                        $output->writeln($e->getMessage());
                        continue;
                    }
                }else{
                    $output->writeln("Order Does not Exist::".$orderItem.PHP_EOL);
                    $output->writeln(PHP_EOL);
                    continue;
                }
            }
            $filenameZip =$fileNewName.$date.'.txt'.'.zip';
            $this->createZipSentMail($path,$fileNameCustom,$filenameZip,$output);
        }catch(\Exception $e)
        {
            $output->writeln($e->getMessage());
        }
    }

    /**
     * @param $output
     * @param $OrderIdsArray
     */
    public function exportOrderFileOrderIds($output, $OrderIdsArray){
        try{
            $storeScope = \Magento\Store\Model\ScopeInterface::SCOPE_STORE;
            $brand = $this->scopeConfig->getValue(self::XML_PATH_BRAND_NAME, $storeScope);
            $varDirectory = $this->filesystem->getDirectoryWrite(
                DirectoryList::VAR_DIR
            );
            $fileName = $brand.'OrderExportNewNew';
            $fileNewName = $fileName;
            $date = (new \DateTime())->format('Y-m-d H:i:s');
            $fileNameCustom = $brand.'OrderExportItem'.$date.'txt';
            $path = $this->directoryList->getPath('var') . DIRECTORY_SEPARATOR . $fileName . "{$date}.txt";
            $orderfile = fopen($path, "w+");
            $progressBar = new \Symfony\Component\Console\Helper\ProgressBar($output, count($OrderIdsArray));
            $progressBar->setFormat(
                '<info>%message%</info> %current%/%max% [%bar%] %percent:3s%% %elapsed% %memory:6s%'
            );
            $progressBar->start();
            $progressBar->setMessage(str_pad('Getting Payload ...', 15, ' ', STR_PAD_RIGHT));
            $progressBar->display();
            foreach ($OrderIdsArray as $orderItem)
            {
                $order = $this->orderFactory->create();
                $order = $order->load($orderItem, 'entity_id');
                if($order->getId())
                {
                    try{
                        try {
                            $output->writeln(PHP_EOL);
                            $progressBar->setMessage(str_pad($order->getIncrementId() . ' ::Sending Create Payload...', 15, ' ', STR_PAD_RIGHT));
                            $output->writeln(PHP_EOL);
                            $progressBar->display();
                            $payload = $this->orderManagement->getCreateOrderPayload($order);
                            $payload= json_encode($payload,true). PHP_EOL.PHP_EOL.PHP_EOL;
                            $output->writeln(PHP_EOL);
                            $output->writeln("Order Create Payload Send::".$order->getIncrementId().PHP_EOL);
                            $output->writeln(PHP_EOL);
                            $output->writeln(PHP_EOL);
                            $progressBar->advance();
                        }catch(\Exception $e)
                        {

                            $payload= ''. PHP_EOL.PHP_EOL.PHP_EOL;
                            
                            continue;
                        }
                        fwrite($orderfile, $payload);
                    }catch(\Exception $e)
                    {
                        $output->writeln($e->getMessage());
                        continue;
                    }

                }else{
                    $output->writeln("Order Does not Exist::".$orderItem.PHP_EOL);
                    $output->writeln(PHP_EOL);
                    continue;
                }
            }
            $filenameZip =$fileNewName.$date.'.txt'.'.zip';
            $this->createZipSentMail($path,$fileNameCustom,$filenameZip,$output);
        }catch(\Exception $e)
        {
            $output->writeln($e->getMessage());
        }

    }


    /**
     * @param $path
     * @param $fileNameCustom
     * @param $filenameZip
     * @param $output
     */
    public function createZipSentMail($path, $fileNameCustom, $filenameZip, $output)
    {
        try{
            $zipArchive = new ZipArchive();
            $fileNameZip = $path . '.zip';
            $filePathZip =  $fileNameZip;
            if ($zipArchive->open($filePathZip, ZipArchive::CREATE) !== TRUE) {
                $this->_logger->debug("unable to create zip file : {$fileNameZip} : on path : {$filePathZip} . aborting... \n");
                exit();
            }
            $zipArchive->addFile($path, $fileNameCustom);
            $zipArchive->close();
            $filePath = $filePathZip;
            $this->helper->sendOrderCreateReport($filePath,$filenameZip,$output);
        }catch(\Exception $e)
        {
            $output->writeln($e->getMessage());
        }

    }

    /**
     * @param null $store
     * @return array|mixed
     */
    public function getConfigValues($store = null)
    {
        $value = $this->scopeConfig->getValue(self::INTERVAL, \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $store);
        $value = $this->unserializeValue($value);
        if ($this->isEncodedArrayFieldValue($value)) {
            $value = $this->decodeArrayFieldValue($value);
        }
        return $value;
    }


    /**
     * @param $value
     * @return array|mixed
     */
    protected function unserializeValue($value)
    {
        if (is_string($value) && !empty($value)) {
            return unserialize($value);
        } else {
            return [];
        }
    }


    /**
     * @param $value
     * @return bool
     */
    public function isEncodedArrayFieldValue($value)
    {

        if (!is_array($value)) {
            return false;
        }
        unset($value['__empty']);
        foreach ($value as $row) {
            if (!is_array($row)
                || !array_key_exists('from_order_id', $row)
                || !array_key_exists('to_order_id', $row)
            ) {
                return false;
            }
        }
        return true;
    }


    /**
     * @param array $value
     * @return array
     */
    protected function decodeArrayFieldValue(array $value)
    {
        $rangeArray = [];
        unset($value['__empty']);
        foreach ($value as $row) {
            if (!is_array($row)
                || !array_key_exists('from_order_id', $row)
                || !array_key_exists('to_order_id', $row)
            ) {
                continue;
            }
            $fromOrderId = $row['from_order_id'];
            $toOrderId = $this->checkValue($row['to_order_id']);
            $rangeArray = range($fromOrderId,$toOrderId);
        }
        return $rangeArray;
    }

    /**
     * @param $values
     * @return |null
     */
    public function checkValue($values)
    {
        return !empty($values) ? $values : null;
    }






}
