<?php
/**
 * OrderExportFromIdsRma.php
 * @package   Altayer\Support\Console
 * @author    Amrendra <amrendragr8@gmail.com>
 */

namespace Altayer\Support\Console;

use Altayer\Sales\Model\Utility;
use Altayer\Support\Model\Helper as Helper;
use Magento\Rma\Model\ResourceModel\Rma\CollectionFactory as rmaCollectionFactory;
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


class OrderExportFromIdsRma extends Command
{
    const NAME = 'sales:order:OrderExportFromIdsRma';

    const ORDER_IDS = 'altayer_order_monitor/order_export_rma/order_ids';

    const XML_PATH_BRAND_NAME = 'general/store_information/name';

    const ORDER_FROM_DATE = 'altayer_order_monitor/order_export_rma/from_date';

    const ORDER_TO_DATE = 'altayer_order_monitor/order_export_rma/to_date';



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
     * OrderExportFromIdsRma constructor.
     * @param Utility $utility
     * @param OrderFactory $orderFactory
     * @param CollectionFactory $collectionFactory
     * @param Csv $csv
     * @param DirectoryList $directoryList
     * @param ResourceConnection $resource
     * @param PsrLogger $logger
     * @param Helper $helper
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param \Altayer\Oms\Model\ReturnManagement $returnManagement
     * @param Filesystem $filesystem
     * @param ObjectManagerInterface $objectManager
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
        \Altayer\Oms\Model\ReturnManagement $returnManagement,
        Filesystem $filesystem,
        ObjectManagerInterface $objectManager,
        rmaCollectionFactory $rmaCollectionFactory,
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
        $this->returnManagement = $returnManagement;
        $this->_objectManager = $objectManager;
        $this->filesystem = $filesystem;
        $this->rmaCollectionFactory = $rmaCollectionFactory;
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this->setName(self::NAME);
        $this->setDescription('Order Cancel Export From Ids');
        parent::configure();
    }

    /**
     * export report orders
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $OrderIds = $this->scopeConfig->getValue(self::ORDER_IDS);
        $orderFromDate = $this->scopeConfig->getValue(self::ORDER_FROM_DATE);
        $ordertodate = $this->scopeConfig->getValue(self::ORDER_TO_DATE);
        $OrderIdsArray = explode(",", $OrderIds);
        if(!is_null($OrderIds) && count($OrderIdsArray))
        {
            $this->exportOrderFileOrderIds($output,$OrderIdsArray);
        }else
        {
            if(!empty($orderFromDate))
            {
                $this->exportOrderFileFromDate($orderFromDate,$output,$ordertodate);
            }
        }

    }


    /**
     * @param $orderFromDate
     * @param $output
     */
    public function exportOrderFileFromDate($orderFromDate, $output,$ordertodate)
    {
        try{
            $storeScope = \Magento\Store\Model\ScopeInterface::SCOPE_STORE;
            $orderCollection = $this->getOrderCollectionFromDate($orderFromDate,$ordertodate);
            if(!$orderCollection)
            {
                $output->writeln("Not Getting any order in this range!!!");
                return $this;
            }
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
            $progressBar = new \Symfony\Component\Console\Helper\ProgressBar($output, count($orderCollection->getAllIds()));
            $progressBar->setFormat(
                '<info>%message%</info> %current%/%max% [%bar%] %percent:3s%% %elapsed% %memory:6s%'
            );

            $progressBar->start();
            $progressBar->setMessage(str_pad('Getting Payload ...', 15, ' ', STR_PAD_RIGHT));
            $progressBar->display();
            if($orderCollection)
            {
                if(count($orderCollection->getAllIds()))
                {
                    foreach ($orderCollection->getAllIds() as $orderId)
                    {
                        $rmaCollection = $this->rmaCollectionFactory->create();
                        $rmaCollection->addFieldToFilter('order_id', ['eq' => $orderId]);
                        foreach($rmaCollection as $rma)
                        {
                            if($rma->getId())
                            {
                                try{
                                    try {
                                        $output->writeln(PHP_EOL);
                                        $progressBar->setMessage(str_pad($rma->getOrderIncrementId() . ' ::Sending Return Payload...', 15, ' ', STR_PAD_RIGHT));
                                        $output->writeln(PHP_EOL);
                                        $progressBar->display();
                                        $payload = $this->returnManagement->getReturnOrderPayload($rma);
                                        $payload= json_encode($payload,true). PHP_EOL.PHP_EOL.PHP_EOL;
                                        $output->writeln(PHP_EOL);
                                        $output->writeln("Order Return Payload Send::".$rma->getOrderIncrementId().PHP_EOL);
                                        $output->writeln(PHP_EOL);
                                        $output->writeln(PHP_EOL);
                                        $progressBar->advance();
                                    }catch(\Exception $e)
                                    {

                                        $payload= ''. PHP_EOL.PHP_EOL.PHP_EOL;
                                        $this->_logger->critical("Return :: Order Payload For Order :::.".$rma->getOrderIncrementId().":::  Error :" . $e->getMessage());
                                        continue;
                                    }
                                    fwrite($orderfile, $payload);
                                }catch(\Exception $e)
                                {
                                    $output->writeln($e->getMessage());
                                    continue;
                                }

                            }
                        }

                    }
                    $filenameZip =$fileNewName.$date.'.txt'.'.zip';
                    $this->createZipSentMail($path,$fileNameCustom,$filenameZip,$output);
                }
            }
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
            $fileNameCustom = $brand.'OrderExportReturnItem'.$date.'txt';
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
                $rmaCollection = $this->rmaCollectionFactory->create();
                $rmaCollection->addFieldToFilter('order_id', ['eq' => $orderItem]);
                if(count($rmaCollection))
                {
                    foreach ($rmaCollection as $rma)
                    {
                        if($rma->getId())
                        {
                            try{
                                try {
                                    $output->writeln(PHP_EOL);
                                    $progressBar->setMessage(str_pad($rma->getOrderIncrementId() . ' ::Sending Return Payload...', 15, ' ', STR_PAD_RIGHT));
                                    $output->writeln(PHP_EOL);
                                    $progressBar->display();
                                    $payload = $this->returnManagement->getReturnOrderPayload($rma);
                                    $payload= json_encode($payload,true). PHP_EOL.PHP_EOL.PHP_EOL;
                                    $output->writeln(PHP_EOL);
                                    $output->writeln("Order Return Payload Send::".$rma->getOrderIncrementId().PHP_EOL);
                                    $output->writeln(PHP_EOL);
                                    $output->writeln(PHP_EOL);
                                    $progressBar->advance();
                                }catch(\Exception $e)
                                {

                                    $payload= ''. PHP_EOL.PHP_EOL.PHP_EOL;
                                    $this->_logger->critical("Return :: Order Payload For Order :::.".$rma->getOrderIncrementId().":::  Error :" . $e->getMessage());
                                    continue;
                                }
                                fwrite($orderfile, $payload);
                            }catch(\Exception $e)
                            {
                                $output->writeln($e->getMessage());
                                continue;
                            }

                        }
                    }
                }else{
                    $output->writeln(PHP_EOL);
                    $output->writeln("Rma Does Not Exist For Order Id::".$orderItem.PHP_EOL);
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
            $this->helper->sendOrderReturnReport($filePath,$filenameZip,$output);
        }catch(\Exception $e)
        {
            $output->writeln($e->getMessage());
        }

    }


    /**
     * @param $orderFromDate
     * @param $ordertodate
     * @return bool|\Magento\Sales\Model\ResourceModel\Order\Collection
     */
    public function getOrderCollectionFromDate($orderFromDate, $ordertodate)
    {
        if(!empty($orderFromDate) && empty($ordertodate))
        {
            $to = date("Y-m-d H:i:s");
            $from = $orderFromDate;
            $orderCollection =  $this->collectionFactory->create()
                ->addFieldToFilter(
                    'created_at', array('from'=>$orderFromDate, 'to'=>$to),
                    'created_at', array('from'=>$to, 'to'=>$orderFromDate)
                )
                ->setOrder(
                    'created_at',
                    'desc'
                );
        }else{
            $to = $ordertodate;
            $from = $orderFromDate;
            $orderCollection =  $this->collectionFactory->create()
                ->addFieldToFilter(
                    'created_at', array('from'=>$orderFromDate, 'to'=>$to),
                    'created_at', array('from'=>$to, 'to'=>$orderFromDate)
                )
                ->setOrder(
                    'created_at',
                    'desc'
                );
        }
        if(count($orderCollection))
        {
            return $orderCollection;
        }
        return false;
    }
    

}
