<?php
/**
 * OrderSendOmsCloudCommand.php
 * @package   Altayer\Support\Console
 * @author    Amrendra <amrendragr8@gmail.com>
 */

namespace Altayer\Support\Console;

use Altayer\Oms\Helper\Data as OmsHelper;
use Altayer\Oms\Model\OmsPayloadFactory;
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
use Symfony\Component\Console\Input\InputOption;


class OrderSendOmsCloudCommand extends Command
{
    const NAME = 'sales:order:OrderSendToOmsCloud';

    const INTERVAL = 'altayer_order_monitor/order_export/ranges';

    const ORDER_IDS = 'altayer_order_monitor/order_export/order_ids';

    const TYPE_FOR_CREATE = 'order/migrate';

    const TYPE_FOR_CANCEL = 'cancel/migrate';

    const TYPE_FOR_RETURN = 'return/migrate';

    const CREATE_TYPE = 'create';

    const CANCEL_TYPE = 'cancel';

    const RETURN_TYPE = 'return';



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
     * OrderSendOmsCloudCommand constructor.
     * @param Utility $utility
     * @param OrderFactory $orderFactory
     * @param CollectionFactory $collectionFactory
     * @param Csv $csv
     * @param DirectoryList $directoryList
     * @param ResourceConnection $resource
     * @param PsrLogger $logger
     * @param Helper $helper
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param \Altayer\Oms\Model\OrderManagement $orderManagement
     * @param Filesystem $filesystem
     * @param ObjectManagerInterface $objectManager
     * @param null $name
     * @param OmsHelper $omsHelper
     * @param OmsPayloadFactory $omsPayloadFactory
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
        OmsHelper $omsHelper,
        OmsPayloadFactory $omsPayloadFactory,
        \Altayer\Oms\Model\OmsService $omsService,
        \Altayer\Oms\Model\CancelManagement $cancelManagement,
        \Altayer\Oms\Model\ReturnManagement $returnManagement,
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
        $this->orderManagement = $orderManagement;
        $this->_objectManager = $objectManager;
        $this->filesystem = $filesystem;
        $this->omsHelper = $omsHelper;
        $this->omsPayloadFactory = $omsPayloadFactory;
        $this->omsService = $omsService;
        $this->cancelManagement = $cancelManagement;
        $this->returnManagement = $returnManagement;
        $this->rmaCollectionFactory = $rmaCollectionFactory;
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this->setName(self::NAME);
        $this->addArgument(
            'Type',
            InputArgument::REQUIRED,
            'Order Type'
        );
        $this->setDescription('Order Send OMS cloud Data');
        parent::configure();
    }

    /**
     * export report orders
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $type = $input->getArgument('Type');
        if(!empty($type))
        {
            $intervalOrderIds = $this->scopeConfig->getValue(self::INTERVAL);
            $OrderIds = $this->scopeConfig->getValue(self::ORDER_IDS);
            $OrderIdsArray = explode(",", $OrderIds);
            $encodedArrayValue = $this->getConfigValues();
            if(!is_null($OrderIds) && count($OrderIdsArray))
            {
                $this->exportOrderDataToOmsCloudOrderIds($output,$OrderIdsArray,$type);
            }
            else{
                if(($encodedArrayValue && is_array($encodedArrayValue)))
                {
                    $this->exportOrderDataToOmsCloud($encodedArrayValue,$output,$type);
                }
            }
        }else{
            $output->writeln("Please Enter The Type To Export The Order TO OMS Cloud");
        }


    }


    /**
     * @param $intervalOrderIds
     * @param $output
     */
    public function exportOrderDataToOmsCloud($intervalOrderIds, $output ,$type)
    {
        $tokenAuth = "";
        $tokenAuth = $this->getAuthToken();
        $startTime = date("Y-m-d H:i:s");
        $baseUrl = $this->omsHelper->getOMSEndPoint();
        if($type == self::CREATE_TYPE)
        {
            $payloadTypeSendToOms = self::TYPE_FOR_CREATE;
            $uri = rtrim($baseUrl, '/') . '/' . $payloadTypeSendToOms;
            if($this->omsHelper->isEnabledOmsCloud() && $this->omsHelper->isEnabledToSendCreateOrder())
            {
                $progressBar = new \Symfony\Component\Console\Helper\ProgressBar($output, count($intervalOrderIds));
                $progressBar->setFormat(
                    '<info>%message%</info> %current%/%max% [%bar%] %percent:3s%% %elapsed% %memory:6s%'
                );
                $progressBar->start();
                $progressBar->setMessage(str_pad('Sending', 15, ' ', STR_PAD_RIGHT));
                $progressBar->display();
                foreach ($intervalOrderIds as $orderItem)
                {
                    $order = $this->orderFactory->create();
                    $order = $order->load($orderItem, 'entity_id');
                    if($order->getId())
                    {
                        $endTime = date("Y-m-d H:i:s");
                        $starttimestamp = strtotime($startTime);
                        $endtimestamp = strtotime($endTime);
                        $difference = abs($endtimestamp - $starttimestamp)/60;
                        if(empty($tokenAuth) || $difference>=25)
                        {
                            $tokenAuth =  $this->getAuthToken();
                        }
                        if(!empty($tokenAuth))
                        {
                            try{
                                $progressBar->setMessage(str_pad($order->getIncrementId() . ' :: Sending Data To Oms For Type Create...', 15, ' ', STR_PAD_RIGHT));
                                $progressBar->display();
                                $output->writeln(PHP_EOL);
                                try{
                                    try{
                                        $payload = $this->orderManagement->getCreateOrderPayload($order);
                                    }catch(\Exception $e)
                                    {
                                        $this->_logger->critical("Oms Cloud Send Data For Type :::'.$type.' ::  Error :" . $e->getMessage());
                                        $output->writeln("Payload Not Getting Or Might Some Error For Order Type ::: ".$type."::".$order->getIncrementId().'::Error:'.$e->getMessage());
                                        $output->writeln(PHP_EOL);
                                        $progressBar->advance();
                                        continue;
                                    }
                                    $omsPayload = $this->omsPayloadFactory->create(['data' => $payload]);
                                    $request =  $this->omsService->removeNullValues($omsPayload->getData());
                                    $response = $this->omsHelper->postDataOms($request,$uri,$tokenAuth);
                                    $responseJson = json_encode($response,true);
                                    if(is_array($response) && array_key_exists('message',$response))
                                    {
                                        $output->writeln($response);
                                        $tokenAuth =  $this->getAuthToken();
                                    }elseif(is_array($response) && array_key_exists('order_no',$response))
                                    {
                                        $output->writeln('Order Number From Response::'.$response['order_no']);
                                    }
                                    $output->writeln("Order Exported to Oms Cloud For Type::: ".$type."::".$order->getIncrementId().PHP_EOL);
                                    $output->writeln(PHP_EOL);
                                    $progressBar->advance();
                                }
                                catch(\Exception $e)
                                {
                                    $this->_logger->critical("Oms Cloud Send Data For Order Type :::".$type." ::  Error :" . $e->getMessage());
                                    $output->writeln("Payload Not Getting Or Might Some Error Order Type ::: ".$type."::".$order->getIncrementId()."::Error:".$e->getMessage());
                                    $output->writeln(PHP_EOL);
                                    $progressBar->advance();
                                    continue;
                                }
                            }catch(\Exception $e)
                            {
                                $this->_logger->critical("Oms Cloud :: Order Not Exported Error For Order TYPE :::".$type."Error :::" . $e->getMessage());
                                $output->writeln("Order Not Exported to Oms Cloud For Order Type ::: ".$type."::".$order->getIncrementId().PHP_EOL);
                                $output->writeln(PHP_EOL);
                                continue;
                            }
                        }else{
                            $output->writeln("Empty Token Auth::");
                            $output->writeln(PHP_EOL);
                            continue;
                        }
                    }else{
                        $output->writeln("Order Does not Exist::".$orderItem.PHP_EOL);
                        $output->writeln(PHP_EOL);
                        continue;
                    }
                }
            }else {
                $output->writeln("Please Enable The '.$type.'Order and push data to OMS cloud");
            }
        }elseif($type == self::CANCEL_TYPE)
        {
            $payloadTypeSendToOms = self::TYPE_FOR_CANCEL;
            $uri = rtrim($baseUrl, '/') . '/' . $payloadTypeSendToOms;
            if($this->omsHelper->isEnabledOmsCloud() && $this->omsHelper->isEnabledToSendCancelOrder())
            {
                $progressBar = new \Symfony\Component\Console\Helper\ProgressBar($output, count($intervalOrderIds));
                $progressBar->setFormat(
                    '<info>%message%</info> %current%/%max% [%bar%] %percent:3s%% %elapsed% %memory:6s%'
                );
                $progressBar->start();
                $progressBar->setMessage(str_pad('Sending', 15, ' ', STR_PAD_RIGHT));
                $progressBar->display();
                foreach ($intervalOrderIds as $orderItem)
                {
                    $order = $this->orderFactory->create();
                    $order = $order->load($orderItem, 'entity_id');
                    if($order->getId())
                    {
                        $endTime = date("Y-m-d H:i:s");
                        $starttimestamp = strtotime($startTime);
                        $endtimestamp = strtotime($endTime);
                        $difference = abs($endtimestamp - $starttimestamp)/60;
                        if(empty($tokenAuth) || $difference>=25)
                        {
                            $tokenAuth =  $this->getAuthToken();
                        }
                        if(!empty($tokenAuth))
                        {
                            try{
                                $progressBar->setMessage(str_pad($order->getIncrementId() . ' ::Sending Data To Oms For Type Cancel ...', 15, ' ', STR_PAD_RIGHT));
                                $progressBar->display();
                                $output->writeln(PHP_EOL);
                                try{
                                    try{
                                        $payload = $this->cancelManagement->getCancelOrderPayload($order);
                                    }catch(\Exception $e)
                                    {
                                        $this->_logger->critical("Oms Cloud Send Data For Type :::'.$type.' ::  Error :" . $e->getMessage());
                                        $output->writeln("Payload Not Getting Or Might Some Error For Order Type ::: ".$type."::".$order->getIncrementId()."::Error:".$e->getMessage());
                                        $output->writeln(PHP_EOL);
                                        $progressBar->advance();
                                        continue;
                                    }
                                    $omsPayload = $this->omsPayloadFactory->create(['data' => $payload]);
                                    $request =  $this->omsService->removeNullValues($omsPayload->getData());
                                    $response = $this->omsHelper->postDataOms($request,$uri,$tokenAuth);
                                    if(is_array($response) && array_key_exists('message',$response))
                                    {
                                        $output->writeln($response);
                                        $tokenAuth =  $this->getAuthToken();
                                    }elseif(is_array($response) && array_key_exists('order_no',$response))
                                    {
                                        $output->writeln('Order Number From Response::'.$response['order_no']);
                                    }
                                    $responseJson = json_encode($response,true);
                                    $output->writeln("Order Exported to Oms Cloud For Type::: ".$type."::".$order->getIncrementId().PHP_EOL);
                                    $output->writeln(PHP_EOL);
                                    $progressBar->advance();
                                }
                                catch(\Exception $e)
                                {
                                    $this->_logger->critical("Oms Cloud Send Data For Order Type :::".$type." ::  Error :" . $e->getMessage());
                                    $output->writeln("Payload Not Getting Or Might Some Error Order Type ::: ".$type."::".$order->getIncrementId()."::Error:".$e->getMessage());
                                    $output->writeln(PHP_EOL);
                                    $progressBar->advance();
                                    continue;
                                }
                            }catch(\Exception $e)
                            {
                                $this->_logger->critical("Oms Cloud :: Order Not Exported Error For Order TYPE :::".$type."Error :::" . $e->getMessage());
                                $output->writeln("Order Not Exported to Oms Cloud For Order Type ::: ".$type."::".$order->getIncrementId().PHP_EOL);
                                $output->writeln(PHP_EOL);
                                continue;
                            }
                        }else{
                            $output->writeln("Empty Token Auth::");
                            $output->writeln(PHP_EOL);
                            continue;
                        }
                    }else{
                        $output->writeln("Order Does not Exist::".$orderItem.PHP_EOL);
                        $output->writeln(PHP_EOL);
                        continue;
                    }
                }
            }else {
                $output->writeln("Please Enable The '.$type.'Order and push data to OMS cloud");
            }
        }elseif ($type == self::RETURN_TYPE)
        {
            $payloadTypeSendToOms = self::TYPE_FOR_RETURN;
            $uri = rtrim($baseUrl, '/') . '/' . $payloadTypeSendToOms;
            if($this->omsHelper->isEnabledOmsCloud() && $this->omsHelper->isEnabledToSendReturnOrder())
            {

                $progressBar = new \Symfony\Component\Console\Helper\ProgressBar($output, count($intervalOrderIds));
                $progressBar->setFormat(
                    '<info>%message%</info> %current%/%max% [%bar%] %percent:3s%% %elapsed% %memory:6s%'
                );
                $progressBar->start();
                $progressBar->setMessage(str_pad('Sending', 15, ' ', STR_PAD_RIGHT));
                $progressBar->display();
                foreach ($intervalOrderIds as $orderItem)
                {
                    $order = $this->orderFactory->create();
                    $order = $order->load($orderItem, 'entity_id');
                    if($order->getId())
                    {
                        
                        $rmaCollection = $this->rmaCollectionFactory->create();
                        $rmaCollection->addFieldToFilter('order_id', ['eq' => $orderItem]);
                        if(count($rmaCollection))
                        {
                            foreach($rmaCollection as $rma)
                            {
                                $endTime = date("Y-m-d H:i:s");
                                $starttimestamp = strtotime($startTime);
                                $endtimestamp = strtotime($endTime);
                                $difference = abs($endtimestamp - $starttimestamp)/60;
                                if(empty($tokenAuth) || $difference>=25)
                                {
                                    $tokenAuth =  $this->getAuthToken();
                                }
                                if(!empty($tokenAuth))
                                {
                                    try{
                                        $progressBar->setMessage(str_pad($order->getIncrementId() . ' ::Sending Data To Oms For Return...', 15, ' ', STR_PAD_RIGHT));
                                        $progressBar->display();
                                        $output->writeln(PHP_EOL);
                                        try{
                                            try{
                                                if($rma->getId())
                                                {
                                                    $payload = $this->returnManagement->getReturnOrderPayload($rma);
                                                }else{
                                                    $output->writeln("Rma Does not Exist For THis Order Id::".$orderItem.PHP_EOL);
                                                    $output->writeln(PHP_EOL);
                                                    continue;
                                                }
                                            }catch(\Exception $e)
                                            {
                                                $this->_logger->critical("Oms Cloud Send Data For Type :::'.$type.' ::  Error :" . $e->getMessage());
                                                $output->writeln("Payload Not Getting Or Might Some Error :: For Type :::".$type."For Order Increment".$order->getIncrementId()."::Error:".$e->getMessage());
                                                $output->writeln(PHP_EOL);
                                                $progressBar->advance();
                                                continue;
                                            }
                                            $omsPayload = $this->omsPayloadFactory->create(['data' => $payload]);
                                            $request =  $this->omsService->removeNullValues($omsPayload->getData());
                                            $response = $this->omsHelper->postDataOms($request,$uri,$tokenAuth);
                                            if(is_array($response) && array_key_exists('message',$response))
                                            {
                                                $output->writeln($response);
                                                $tokenAuth =  $this->getAuthToken();
                                            }elseif(is_array($response) && array_key_exists('order_no',$response))
                                            {
                                                $output->writeln('Order Number From Response::'.$response['order_no']);
                                            }
                                            $responseJson = json_encode($response,true);
                                            $output->writeln("Order Exported to Oms Cloud For Order Type::: ".$type."::".$order->getIncrementId().PHP_EOL);
                                            $output->writeln(PHP_EOL);
                                            $progressBar->advance();
                                        }
                                        catch(\Exception $e)
                                        {
                                            $this->_logger->critical("Oms Cloud Send Data For Type ".$type."::  Error :" . $e->getMessage());
                                            $output->writeln("Payload Not Getting Or Might Some For Type ".$type."::  Error :".$order->getIncrementId()."::Error:".$e->getMessage());
                                            $output->writeln(PHP_EOL);
                                            $progressBar->advance();
                                            continue;
                                        }
                                    }catch(\Exception $e)
                                    {
                                        $this->_logger->critical("Oms Cloud :: Order Not Exported Error For Order TYPE :::".$type."Error :::" . $e->getMessage());
                                        $output->writeln("Order Not Exported to Oms Cloud For Order Type ::: ".$type."::".$order->getIncrementId().PHP_EOL);
                                        $output->writeln(PHP_EOL);
                                        continue;
                                    }
                                }else{
                                    $output->writeln("Empty Token Auth::");
                                    $output->writeln(PHP_EOL);
                                    continue;
                                }
                            } 
                        }else{
                            $output->writeln("Rma Does not Exist For This Order Id::".$orderItem.PHP_EOL);
                            $output->writeln(PHP_EOL);
                            continue;
                        }
                    }else{
                        $output->writeln("Order Does not Exist::".$orderItem.PHP_EOL);
                        $output->writeln(PHP_EOL);
                        continue;
                    }
                }
            }else {
                $output->writeln("Please Enable The '.$type.'Order and push data to OMS cloud");
            }
        }else{
            $output->writeln("Please Enter the create,cancel,return Type Order for push data to OMS cloud");
        }

    }

    /**
     * @param $output
     * @param $OrderIdsArray
     */
    public function exportOrderDataToOmsCloudOrderIds($output, $OrderIdsArray,$type)
    {
        $tokenAuth = "";
        $tokenAuth = $this->getAuthToken();
        $startTime = date("Y-m-d H:i:s");
        $username = $this->omsHelper->getUsernameForOmsCloud();
        $password = $this->omsHelper->getPasswordForOmsCloud();
        $baseUrl = $this->omsHelper->getOMSEndPoint();
        if($type == self::CREATE_TYPE)
        {
            $payloadTypeSendToOms = self::TYPE_FOR_CREATE;
            $uri = rtrim($baseUrl, '/') . '/' . $payloadTypeSendToOms;
            if($this->omsHelper->isEnabledOmsCloud() && $this->omsHelper->isEnabledToSendCreateOrder())
            {
                $progressBar = new \Symfony\Component\Console\Helper\ProgressBar($output, count($OrderIdsArray));
                $progressBar->setFormat(
                    '<info>%message%</info> %current%/%max% [%bar%] %percent:3s%% %elapsed% %memory:6s%'
                );
                $progressBar->start();
                $progressBar->setMessage(str_pad('Sending ...', 15, ' ', STR_PAD_RIGHT));
                $progressBar->display();
                foreach ($OrderIdsArray as $orderItem) {
                    $order = $this->orderFactory->create();
                    $order = $order->load($orderItem, 'entity_id');
                    if ($order->getId()) {
                        $endTime = date("Y-m-d H:i:s");
                        $starttimestamp = strtotime($startTime);
                        $endtimestamp = strtotime($endTime);
                        $difference = abs($endtimestamp - $starttimestamp)/60;
                        if(empty($tokenAuth) || $difference>=25)
                        {
                            $tokenAuth =  $this->omsHelper->getTokenAuth($username,$password);
                        }
                        if(!empty($tokenAuth))
                        {
                            try{
                                $progressBar->setMessage(str_pad($order->getIncrementId() . ' ::Sending Data To Oms For Create Type...', 15, ' ', STR_PAD_RIGHT));
                                $progressBar->display();
                                $output->writeln(PHP_EOL);
                                try{
                                    $payload = $this->orderManagement->getCreateOrderPayload($order);
                                    $omsPayload = $this->omsPayloadFactory->create(['data' => $payload]);
                                    $request =  $this->omsService->removeNullValues($omsPayload->getData());
                                    $response = $this->omsHelper->postDataOms($request,$uri,$tokenAuth);
                                    $responseJson = json_encode($response,true);
                                    if(is_array($response) && array_key_exists('message',$response))
                                    {
                                        $output->writeln($response);
                                        $tokenAuth =  $this->getAuthToken();
                                    }elseif(is_array($response) && array_key_exists('order_no',$response))
                                    {
                                        $output->writeln('Order Number From Response::'.$response['order_no']);
                                    }
                                    $output->writeln("Order Exported to Oms Cloud For Order Type ::: ".$type."::".$order->getIncrementId().PHP_EOL);
                                    $output->writeln(PHP_EOL);
                                    $progressBar->advance();
                                }
                                catch(\Exception $e)
                                {
                                    $this->_logger->critical("Oms Cloud Send Data :: For Order Type :::".$type."::  Error :" . $e->getMessage());
                                    $output->writeln("Payload Not Getting Or Might Some Error ::".$order->getIncrementId()."::Error: ".$e->getMessage());
                                    $output->writeln(PHP_EOL);
                                    $progressBar->advance();
                                    continue;
                                }
                            }catch(\Exception $e)
                            {
                                $this->_logger->critical("Oms Cloud :: Order Not Exported :: For Order Type :::".$type."::  Error :" . $e->getMessage());
                                $output->writeln("Order Not Exported to Oms Cloud:: For Order Type :::".$type."::".$order->getIncrementId().PHP_EOL);
                                $output->writeln(PHP_EOL);
                                continue;
                            }
                        }else{
                            $output->writeln("Empty Token Auth::");
                            $output->writeln(PHP_EOL);
                            continue;
                        }
                    }else{
                        $output->writeln("Order Does not Exist::".$orderItem.PHP_EOL);
                        $output->writeln(PHP_EOL);
                        continue;
                    }
                }
            }else {
                $output->writeln("Please Enable The '.$type.'Order and push data to OMS cloud");
            }
        }elseif($type == self::CANCEL_TYPE)
        {
            $payloadTypeSendToOms = self::TYPE_FOR_CANCEL;
            $uri = rtrim($baseUrl, '/') . '/' . $payloadTypeSendToOms;
            if($this->omsHelper->isEnabledOmsCloud() && $this->omsHelper->isEnabledToSendCancelOrder())
            {

                $progressBar = new \Symfony\Component\Console\Helper\ProgressBar($output, count($OrderIdsArray));
                $progressBar->setFormat(
                    '<info>%message%</info> %current%/%max% [%bar%] %percent:3s%% %elapsed% %memory:6s%'
                );
                $progressBar->start();
                $progressBar->setMessage(str_pad('Sending ...', 15, ' ', STR_PAD_RIGHT));
                $progressBar->display();
                foreach ($OrderIdsArray as $orderItem) {
                    $order = $this->orderFactory->create();
                    $order = $order->load($orderItem, 'entity_id');
                    if ($order->getId()) {
                        $endTime = date("Y-m-d H:i:s");
                        $starttimestamp = strtotime($startTime);
                        $endtimestamp = strtotime($endTime);
                        $difference = abs($endtimestamp - $starttimestamp)/60;
                        if(empty($tokenAuth)|| $difference>=25)
                        {
                            $tokenAuth =  $this->omsHelper->getTokenAuth($username,$password);
                        }
                        if(!empty($tokenAuth))
                        {
                            try{
                                $progressBar->setMessage(str_pad($order->getIncrementId() . ' ::Sending Data To Oms For Cancel Type...', 15, ' ', STR_PAD_RIGHT));
                                $progressBar->display();
                                $output->writeln(PHP_EOL);
                                try{
                                    $payload = $this->cancelManagement->getCancelOrderPayload($order);
                                    $omsPayload = $this->omsPayloadFactory->create(['data' => $payload]);
                                    $request =  $this->omsService->removeNullValues($omsPayload->getData());
                                    $response = $this->omsHelper->postDataOms($request,$uri,$tokenAuth);
                                    if(is_array($response) && array_key_exists('message',$response))
                                    {
                                        $output->writeln($response);
                                        $tokenAuth =  $this->getAuthToken();
                                    }elseif(is_array($response) && array_key_exists('order_no',$response))
                                    {
                                        $output->writeln('Order Number From Response::'.$response['order_no']);
                                    }
                                    $responseJson = json_encode($response,true);
                                    $output->writeln("Order Exported to Oms Cloud For Order Type ::: ".$type."::".$order->getIncrementId().PHP_EOL);
                                    $output->writeln(PHP_EOL);
                                    $progressBar->advance();
                                }
                                catch(\Exception $e)
                                {
                                    $this->_logger->critical("Oms Cloud Send Data :: For Order Type :::".$type."::  Error :" . $e->getMessage());
                                    $output->writeln("Payload Not Getting Or Might Some Error ::".$order->getIncrementId()."::Error: ".$e->getMessage());
                                    $output->writeln(PHP_EOL);
                                    $progressBar->advance();
                                    continue;
                                }
                            }catch(\Exception $e)
                            {
                                $this->_logger->critical("Oms Cloud :: Order Not Exported :: For Order Type :::".$type."::  Error :" . $e->getMessage());
                                $output->writeln("Order Not Exported to Oms Cloud:: For Order Type :::".$type."::".$order->getIncrementId().PHP_EOL);
                                $output->writeln(PHP_EOL);
                                continue;
                            }
                        }else{
                            $output->writeln("Empty Token Auth::");
                            $output->writeln(PHP_EOL);
                            continue;
                        }
                    }else{
                        $output->writeln("Order Does not Exist::".$orderItem.PHP_EOL);
                        $output->writeln(PHP_EOL);
                        continue;
                    }
                }
            }else {
                $output->writeln("Please Enable The '.$type.'Order and push data to OMS cloud");
            }
        }elseif ($type == self::RETURN_TYPE)
        {
            $payloadTypeSendToOms = self::TYPE_FOR_RETURN;
            $uri = rtrim($baseUrl, '/') . '/' . $payloadTypeSendToOms;
            if($this->omsHelper->isEnabledOmsCloud() && $this->omsHelper->isEnabledToSendReturnOrder())
            {

                $progressBar = new \Symfony\Component\Console\Helper\ProgressBar($output, count($OrderIdsArray));
                $progressBar->setFormat(
                    '<info>%message%</info> %current%/%max% [%bar%] %percent:3s%% %elapsed% %memory:6s%'
                );
                $progressBar->start();
                $progressBar->setMessage(str_pad('Sending ...', 15, ' ', STR_PAD_RIGHT));
                $progressBar->display();
                foreach ($OrderIdsArray as $orderItem) {
                    $order = $this->orderFactory->create();
                    $order = $order->load($orderItem, 'entity_id');
                    if($order->getId())
                    {
                        $rmaCollection = $this->rmaCollectionFactory->create();
                        $rmaCollection->addFieldToFilter('order_id', ['eq' => $orderItem]);
                        if(count($rmaCollection))
                        {
                            foreach($rmaCollection as $rma)
                            {
                                $endTime = date("Y-m-d H:i:s");
                                $starttimestamp = strtotime($startTime);
                                $endtimestamp = strtotime($endTime);
                                $difference = abs($endtimestamp - $starttimestamp)/60;
                                if(empty($tokenAuth) ||$difference>=25)
                                {
                                    $tokenAuth =  $this->getAuthToken();
                                }
                                if(!empty($tokenAuth))
                                {
                                    try{
                                        $progressBar->setMessage(str_pad($order->getIncrementId() . ' ::Sending Data To Oms For Return...', 15, ' ', STR_PAD_RIGHT));
                                        $progressBar->display();
                                        $output->writeln(PHP_EOL);
                                        try{
                                            try{
                                                if($rma->getId())
                                                {
                                                    $payload = $this->returnManagement->getReturnOrderPayload($rma);
                                                }else{
                                                    $output->writeln("Rma Does not Exist For This Order Id::".$orderItem.PHP_EOL);
                                                    $output->writeln(PHP_EOL);
                                                    continue;
                                                }
                                            }catch(\Exception $e)
                                            {
                                                $this->_logger->critical("Oms Cloud Send Data For Type :::'.$type.' ::  Error :" . $e->getMessage());
                                                $output->writeln("Payload Not Getting Or Might Some Error :: For Type :::".$type."For Order Increment".$order->getIncrementId()."::Error:".$e->getMessage());
                                                $output->writeln(PHP_EOL);
                                                $progressBar->advance();
                                                continue;
                                            }
                                            $omsPayload = $this->omsPayloadFactory->create(['data' => $payload]);
                                            $request =  $this->omsService->removeNullValues($omsPayload->getData());
                                            $response = $this->omsHelper->postDataOms($request,$uri,$tokenAuth);
                                            if(is_array($response) && array_key_exists('message',$response))
                                            {
                                                $output->writeln($response);
                                                $tokenAuth =  $this->getAuthToken();
                                            }elseif(is_array($response) && array_key_exists('order_no',$response))
                                            {
                                                $output->writeln('Order Number From Response::'.$response['order_no']);
                                            }
                                            $responseJson = json_encode($response,true);
                                            $output->writeln("Order Exported to Oms Cloud For Order Type::: ".$type."::".$order->getIncrementId().PHP_EOL);
                                            $output->writeln(PHP_EOL);
                                            $progressBar->advance();
                                        }
                                        catch(\Exception $e)
                                        {
                                            $this->_logger->critical("Oms Cloud Send Data For Type ".$type."::  Error :" . $e->getMessage());
                                            $output->writeln("Payload Not Getting Or Might Some For Type ".$type."::  Error :".$order->getIncrementId()."::Error:".$e->getMessage());
                                            $output->writeln(PHP_EOL);
                                            $progressBar->advance();
                                            continue;
                                        }
                                    }catch(\Exception $e)
                                    {
                                        $this->_logger->critical("Oms Cloud :: Order Not Exported Error For Order TYPE :::".$type."Error :::" . $e->getMessage());
                                        $output->writeln("Order Not Exported to Oms Cloud For Order Type ::: ".$type."::".$order->getIncrementId().PHP_EOL);
                                        $output->writeln(PHP_EOL);
                                        continue;
                                    }
                                }else{
                                    $output->writeln("Empty Token Auth::");
                                    $output->writeln(PHP_EOL);
                                    continue;
                                }
                            }  
                        }else{
                            $output->writeln(PHP_EOL);
                            $output->writeln("Rma Does Not Exist For Order Id::".$orderItem.PHP_EOL);
                            $output->writeln(PHP_EOL);
                            continue; 
                        }
                    }else{
                        $output->writeln("Order Does not Exist::".$orderItem.PHP_EOL);
                        $output->writeln(PHP_EOL);
                        continue;
                    }
                }
            }else {
                $output->writeln("Please Enable The '.$type.'Order and push data to OMS cloud");
            }
        }else{
            $output->writeln("Please Enter the create,cancel,return Type Order for push data to OMS cloud");
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
    
    public function getAuthToken()
    {
        $username = $this->omsHelper->getUsernameForOmsCloud();
        $password = $this->omsHelper->getPasswordForOmsCloud();
        $tokenAuth =  $this->omsHelper->getTokenAuth($username,$password);
        return $tokenAuth;

    }

}
