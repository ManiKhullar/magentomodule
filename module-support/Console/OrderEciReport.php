<?php
/**
 * OrderTransactionReport.php
 * @package   Altayer\Support\Console
 * @author    Amrendra <amrendragr8@gmail.com>
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


class OrderEciReport extends Command
{
    const NAME = 'sales:order:OrderEciReport';

    const INTERVAL = 'altayer_order_monitor/eci_reports/inerval_day';


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
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this->setName(self::NAME);
        $this->setDescription('Order Eci Report Information');
        parent::configure();
    }

    /**
     * payment report orders
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $intervalDay = $this->scopeConfig->getValue(self::INTERVAL);
        $startDate = '';
        $endDate = '';
        if(!empty($intervalDay))
        {
            $to = date("Y-m-d h:i:s");
            $from = strtotime( '-'.$intervalDay.'day', strtotime($to));
            $from = date('Y-m-d h:i:s', $from);
            $startDate  = $to;
            $endDate  = $from;
        }else{
            $to = date("Y-m-d h:i:s");
            $from = strtotime( '- 120 day', strtotime($to));
            $from = date('Y-m-d h:i:s', $from);
            $startDate  = $to;
            $endDate  = $from;
        }
        $this->exportCsvFile($startDate,$endDate,$output);

    }

    /**
     * @param $startDate
     * @param $endDate
     */
    protected function exportCsvFile($startDate, $endDate,$output)
    {
        try{
            $this->collection = $this->getCollection($startDate,$endDate);
            $exportData = [];
            $exportData[] = $this->getHeaders();
            
            if (count($this->collection) > 0) {
                foreach ($this->collection as $order) {
                        if((in_array($order->getPayment()->getAdditionalInformation('bin_country'), ["US", "JP", "HK", "GB", "IN", "AR", "BR", "CH"]))
                            || (in_array( $order->getPayment()->getAdditionalInformation('eci'), ["00", "01", "06", "07"]))
                        )
                        {
                            /** Build  Data array */
                            $billingData = [];
                            $streetAddress = implode(' ',$order->getBillingAddress()->getStreet());
                            $exportData[] = [
                                $this->convertDateAsiaTimeZone($order->getCreatedAt()),
                                $order->getIncrementId(),
                                $order->getCustomerEmail(),
                                $order->getCustomerFirstName()." ".$order->getCustomerLastName(),
                                $order->getPayment()->getAdditionalInformation() ? $order->getPayment()->getAdditionalInformation('eci'):"",
                                $order->getPayment()->getAdditionalInformation() ? $order->getPayment()->getAdditionalInformation('cc_trans_id'):"",
                                $order->getPayment()->getAdditionalInformation()? $order->getPayment()->getAdditionalInformation('card_number'):"",
                                $order->getPayment()->getAdditionalInformation() ? $order->getPayment()->getAdditionalInformation('card_type'):"",
                                $order->getPayment()->getAdditionalInformation() ? $order->getPayment()->getAdditionalInformation('card_type_name'):"",
                                $order->getPayment()->getAdditionalInformation() ? $order->getPayment()->getAdditionalInformation('card_expiry_date'):"",
                                $order->getPayment()->getAdditionalInformation() ? $order->getPayment()->getAdditionalInformation('card_expiry_month'):"",
                                $order->getPayment()->getAdditionalInformation() ? $order->getPayment()->getAdditionalInformation('card_expiry_year'):"",
                                $order->getPayment()->getAdditionalInformation() ? $order->getPayment()->getAdditionalInformation('avs_result_code'):"",
                                $order->getPayment()->getAdditionalInformation() ? $order->getPayment()->getAdditionalInformation('fraud_score'):"",
                                $order->getPayment()->getAdditionalInformation() ? $order->getPayment()->getAdditionalInformation('decision'):"",
                                $order->getPayment()->getAdditionalInformation() ? $order->getPayment()->getAdditionalInformation('reference_number'):"",
                                $order->getPayment()->getAdditionalInformation() ? $order->getPayment()->getAdditionalInformation('bin_country'):"",
                                $order->getPayment()->getAdditionalInformation() ? $order->getPayment()->getAdditionalInformation('bin_number'):"",
                                $order->getPayment()->getAdditionalInformation() ? $order->getPayment()->getAdditionalInformation('fraud_suspected'):"",
                                $order->getPayment()->getAdditionalInformation() ? $order->getPayment()->getAdditionalInformation('transaction_amount'):"",
                                $order->getPayment()->getMethod() ? $order->getPayment()->getMethod():"",
                                $order->getBillingAddress()->getRegion(),
                                $order->getBillingAddress()->getPostCode(),
                                $streetAddress,
                                $order->getBillingAddress()->getCity(),
                                $order->getBillingAddress()->getCountryId()
                            ];
                        }
                }
                if(count($exportData)==1)
                {
                    $exportData[] = ["There is not any data for Eci's"];
                }
                $filename = 'OrderEciReport';
                $date = (new \DateTime())->format('Y-m-d H:i:s');
                $path = $this->directoryList->getPath('var') . DIRECTORY_SEPARATOR . $filename . "{$date}.csv";
                $this->csv->saveData($path, $exportData);
                $this->helper->sendEciReportUae($output);
            }
        }catch(\Exception $e){
            $this->_logger->debug($e->getMessage());
        }

    }

    /**
     * @return string[]
     */
    protected function getHeaders(){
        try {
            $data = [
                'Created At',
                'Increment_Id',
                'Email',
                'Customer_Name',
                'Eci',
                'Transaction Id',
                'Card Number',
                'Card Type',
                'Card Type Name',
                'Card Expiry Date',
                'Card Expiry Month',
                'Card Expiry Year',
                'Avs Result Code',
                'Fraud Score',
                'Decision',
                'Reference Number',
                'Bin Country',
                'Bin Number',
                'Fraud Suspected',
                'Transaction Amount',
                'Method Title',
                'Region',
                'PostCode',
                'Street',
                'City',
                'Country'
            ];
            return $data;
        }catch(\Exception $e){
            $this->_logger->debug($e->getMessage());
        }

    }

    /**
     * @param $startDate
     * @param $endDate
     * @return \Magento\Sales\Model\ResourceModel\Order\Collection
     */
    protected function getCollection($startDate, $endDate){
        try {
            $orderCollection = $this->collectionFactory->create();
            if($startDate  && $endDate)
            {
                $collection = $orderCollection
                    ->addFieldToFilter('created_at', array('from'=>$endDate, 'to'=>$startDate))
                    ->addFieldToFilter("order_currency_code","AED")
                    ->setOrder(
                        'created_at',
                        'desc'
                    );
            }
            return $collection;
        }catch(\Exception $e){
            $this->_logger->debug($e->getMessage());
        }

    }

    /**
     * @param $date
     * @return string
     * @throws \Exception
     */
    protected function convertDateAsiaTimeZone($date)
    {
        $date = new \DateTime($date, new \DateTimeZone('UTC'));
        $date->setTimezone(new \DateTimeZone('Asia/Dubai'));
        $dateTime = $date->format('m-d-Y H:i:s');
        return $dateTime;
    }


}
