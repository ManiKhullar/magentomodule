<?php
/**
 * @author   Mani <kmanidev6@gmail.com>
 * @package Altayer_Support
 * @date 27/09/2020
 * */

namespace Altayer\Support\Model;

use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\ObjectManager;
use phpseclib\Net\SFTP;
use Magento\Framework\Mail\Message;
use Magento\Framework\Mail\Transport;

/**
 * Class Helper
 * @package Altayer\Support\Model
 */
class Helper
{
    const XML_PATH_BRAND_NAME = 'general/store_information/name';
    const XML_PATH_SEGMENTATION_ENABLE = 'altayer_order_monitor/marketing_data_segmentation/enable';
    const XML_PATH_SEGMENTATION_THRESHOLD = 'altayer_order_monitor/marketing_data_segmentation/created_at';
    const XML_PATH_SEGMENTATION_EMAILIDS = 'altayer_order_monitor/marketing_data_segmentation/email_ids';

    const XML_PATH_OMSERROR_ENABLE = 'altayer_order_monitor/oms_error/enable';
    const XML_PATH_OMSERROR_IGNORE_TEXT = 'altayer_order_monitor/oms_error/ignore_error';
    const XML_PATH_OMSERROR_EMAILIDS = 'altayer_order_monitor/oms_error/email_ids';
    const XML_PATH_OMSERROR_THRESHOLS = 'altayer_order_monitor/oms_error/threshold_for_error';
    const XML_PATH_OMSERROR_FROMADDRESS = 'altayer_order_monitor/oms_error/from_email';
    const XML_PATH_OMSERROR_TOADDRESS = 'altayer_order_monitor/oms_error/to_email';
    const XML_PATH_REPORT_ORDERS = 'altayer_order_monitor/report_order/create_reports';
    const XML_PATH_REPORT_FROMADDRESS = 'altayer_order_monitor/report_order/from_email';
    const XML_PATH_REPORT_TOADDRESS = 'altayer_order_monitor/report_order/to_email';
    const XML_PATH_EXPORT_ORDERS = 'altayer_order_monitor/order_export/create_order_export';
    const XML_PATH_EXPORT_TOADDRESS = 'altayer_order_monitor/order_export/to_email';
    const XML_PATH_MISSING_CATEGORY_EMAIL = 'altayer_order_monitor/Missing_reports/to_email';

    const XML_PATH_ECI_REPORT_ORDERS = 'altayer_order_monitor/eci_reports/create_eci_reports';
    const XML_PATH_ECI_REPORT_FROMADDRESS = 'altayer_order_monitor/eci_reports/to_email';


    /**
     *
     * @var \Magento\Quote\Model\QuoteFactory
     */
    protected $quoteFactory;

    /**
     * @var \Magento\Catalog\Model\ProductFactory
     */
    protected $productFactory;

    /**
     * @var \Psr\Log\LoggerInterface
     */
    protected $logger;

    /**
     * @var \Magento\Framework\App\ResourceConnection
     */
    protected $_resource;

    /**
     * @var \Magento\Eav\Model\Config
     */
    protected $config;

    /**
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * @var \Magento\Catalog\Helper\ImageFactory
     */
    protected $imageHelperFactory;

    /**
     * @var \Magento\SalesRule\Model\Rule
     */
    protected $ruleFactory;

    /**
     * @var \Magento\SalesRule\Model\Coupon\Massgenerator
     */
    protected $massgenerator;

    /**
     * @var \Altayer\Support\Model\AtgReminder
     */
    protected $atgReminderFactory;


    /**
     * @var \Magento\Framework\Mail\Message
     */
    protected $message;

    /**
     * @var \Magento\Framework\Mail\Transport
     */
    protected $transport;

    /**
     * Helper constructor.
     * @param \Psr\Log\LoggerInterface $logger
     * @param \Magento\Quote\Model\QuoteFactory $quoteFactory
     * @param \Magento\Catalog\Model\ProductFactory $productFactory
     * @param \Magento\Framework\App\ResourceConnection $resource
     * @param \Magento\Eav\Model\Config $config
     * @param \Magento\Catalog\Helper\ImageFactory $imageHelperFactory
     * @param \Magento\Framework\Filesystem $filesystem
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param \Magento\SalesRule\Model\RuleFactory $ruleFactory
     * @param \Magento\SalesRule\Model\Coupon\Massgenerator $massgenerator
     * @param AtgReminderFactory $atgReminderFactory
     * @param Message $message
     * @param Transport $transport
     * @throws \Magento\Framework\Exception\FileSystemException
     */
    public function __construct(
        \Psr\Log\LoggerInterface $logger,
        \Magento\Quote\Model\QuoteFactory $quoteFactory,
        \Magento\Catalog\Model\ProductFactory $productFactory,
        \Magento\Framework\App\ResourceConnection $resource,
        \Magento\Eav\Model\Config $config,
        \Magento\Catalog\Helper\ImageFactory $imageHelperFactory,
        \Magento\Framework\Filesystem $filesystem,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\SalesRule\Model\RuleFactory $ruleFactory,
        \Magento\SalesRule\Model\Coupon\Massgenerator $massgenerator,
        \Altayer\Support\Model\AtgReminderFactory $atgReminderFactory,
        Message $message,
        Transport $transport
    )
    {
        $this->logger = $logger;
        $this->quoteFactory = $quoteFactory;
        $this->productFactory = $productFactory;
        $this->_resource = $resource;
        $this->config = $config;
        $this->imageHelperFactory = $imageHelperFactory;
        $this->directory = $filesystem->getDirectoryWrite(DirectoryList::VAR_DIR);
        $this->scopeConfig = $scopeConfig;
        $this->ruleFactory = $ruleFactory;
        $this->massgenerator = $massgenerator;
        $this->atgReminderFactory = $atgReminderFactory;
        $this->message = $message;
        $this->transport = $transport;
    }

    /**
     * This will send app data segmentation to marketing team
     * @param $output
     */
    public function sendAppDataSegmentation($output)
    {

        if (empty($this->scopeConfig->getValue(Self::XML_PATH_SEGMENTATION_ENABLE))) {
            $this->logger->debug("APP_DATA_SEGMENTATION: is disabled");
            return;
        }

        $startTime = microtime(true);
        $this->logger->debug("APP_DATA_SEGMENTATION: Start preparing the data");

        $storeScope = \Magento\Store\Model\ScopeInterface::SCOPE_STORE;
        $brand = $this->scopeConfig->getValue(self::XML_PATH_BRAND_NAME);
        $exitTime = trim($this->scopeConfig->getValue(Self::XML_PATH_SEGMENTATION_THRESHOLD)) . " DAY ";

        $header = [
            'email', 'phone', 'event_name', 'event_time', 'value', 'currency', 'order_id', 'contents'
        ];

        $connection = $this->_resource->getConnection();
        $this->directory->create('export');
        $messageFilter = '';

        $sql = " SELECT     Sha2(ord.customer_email,256)                                                                             AS email,
           Sha2(addr.telephone,256)                                                                                                  AS phone ,
           'Purchase'                                                                                                                AS event_name ,
           ord.created_at                                                                                                            AS event_time,
           ord.grand_total                                                                                                           AS value ,
           ord.base_currency_code                                                                                                    AS currency ,
           ord.increment_id                                                                                                          AS order_id ,
            Concat('[',Group_concat(Concat('{id:\"',item.sku,'\"'),\",\",Concat('quantity:',Round(item.qty_ordered),'}')),']')       AS contents
            FROM       sales_order ord 
            INNER JOIN sales_order_address addr 
            ON         ord.entity_id = addr.parent_id 
            AND        addr.address_type = 'shipping' 
            INNER JOIN sales_order_item item 
            where      ord.entity_id = item.order_id 
            AND        ord.device IN ('ios', 'android') 
            AND        date(ord.created_at) > curdate() - interval " . $exitTime . "
            GROUP BY   ord.increment_id 
            ORDER BY   ord.created_at DESC";

        try {
            $output->writeln($sql);
            $records = $connection->fetchAll($sql);
            if (empty($records)) {
                $this->logger->debug("APP_DATA_SEGMENTATION: No App segmentation data forund from past " . $exitTime . ' Days');
                return;
            }
            $response = [];
            $i = 0;
            foreach ($records as $data) {
                $response[$i]['email'] = $data['email'];
                $response[$i]['phone'] = $data['phone'];
                $response[$i]['event_name'] = $data['event_name'];
                $response[$i]['event_time'] = $data['event_time'];
                $response[$i]['value'] = $data['value'];
                $response[$i]['currency'] = $data['currency'];
                $response[$i]['order_id'] = $data['order_id'];
                $response[$i]['contents'] = $data['contents'];
                $i++;
            }
            $filename = $brand . '_' . 'App_Data_' .  date('d-M-Y') . '.csv';
            $stream = $this->directory->openFile('export/' . $filename, 'w+');
            $stream->lock();
            $stream->writeCsv($header);
            foreach ($response as $csvData) {
                $data = [];
                $data[] = $csvData['email'];
                $data[] = $csvData['phone'];
                $data[] = $csvData['event_name'];
                $data[] = $csvData['event_time'];
                $data[] = $csvData['value'];
                $data[] = $csvData['currency'];
                $data[] = $csvData['order_id'];
                $data[] = $csvData['contents'];
                $stream->writeCsv($data);
            }

            $fromAddress = $this->scopeConfig->getValue(self::XML_PATH_OMSERROR_FROMADDRESS);
            $toAddress = $this->scopeConfig->getValue(self::XML_PATH_SEGMENTATION_EMAILIDS);
            $subject = $brand . "App Segmentation data : " . date('d-M-Y His');
            $bodyContent = "Please find the attached data ";

            $this->sendMail(DirectoryList::VAR_DIR . '/export/', $filename, $fromAddress, $toAddress, $subject, $bodyContent);
            unlink(DirectoryList::VAR_DIR . '/export/' . $filename);

        } catch (\Exception $e) {
            $this->logger->debug("APP_DATA_SEGMENTATION :: Error :: " . $e->getMessage() . " - " . $e->getLine());
            $output->writeln("APP_DATA_SEGMENTATION : Something went worng." . $e->getMessage() . $e->getLine());
        } finally {
            $endTime = microtime(true);
            $output->writeln("Time taken to process APP_DATA_SEGMENTATION data : " . round($endTime - $startTime) . " Seconds ");
            $this->logger->debug("APP_DATA_SEGMENTATION :: Time taken to generate app segmentation data : " . round($endTime - $startTime) . ' Seconds ' . "\n");
        }

    }

    /** This will send oms error to oms_support team
     * @param $output
     */
    public function sendOMSError($output)
    {
        if (empty($this->scopeConfig->getValue(Self::XML_PATH_OMSERROR_ENABLE))) {
            $this->logger->debug("OMS_ALERT_MESSAGE: Send OMS Error is disabled");
            return;
        }

        $startTime = microtime(true);

        $storeScope = \Magento\Store\Model\ScopeInterface::SCOPE_STORE;
        $brand = $this->scopeConfig->getValue(self::XML_PATH_BRAND_NAME, $storeScope);
        $exitTime = "-" . trim($this->scopeConfig->getValue(Self::XML_PATH_OMSERROR_THRESHOLS, $storeScope)) . " DAY";

        $header = ['order id', 'error message', 'created at'];
        $ignoreText = $this->scopeConfig->getValue(Self::XML_PATH_OMSERROR_IGNORE_TEXT);
        $data = "";
        if (!empty($ignoreText)) {
            $ignoreTextArray = explode(',', $ignoreText);
            if (!empty($ignoreTextArray)) {
                foreach ($ignoreTextArray as $ignore) {
                    $data = $data . " AND h.message NOT LIKE '%" . $ignore . "%'";
                }
            }
        }
        $connection = $this->_resource->getConnection();
        $this->directory->create('export');
        $messageFilter = '';
        $sql = "SELECT DISTINCT( h.order_id ) as entity_id, 
                so.increment_id as order_id, 
                h.message as error_message, 
                h.status as status, 
                h.created_at as created_at
                FROM   (SELECT m1.* 
                        FROM   oms_history m1 
                               LEFT JOIN oms_history m2 
                                      ON ( m1.order_id = m2.order_id 
                                           AND m1.oms_history_id < m2.oms_history_id ) 
                        WHERE  m2.oms_history_id IS NULL 
                        ORDER  BY m1.created_at desc) h 
                       LEFT JOIN sales_order so 
                              ON so.entity_id = h.order_id 
                WHERE  h.status = -1 
                       AND h.type_id = 'order' 
                       AND h.created_at >= Date_add(Curdate(), interval " . $exitTime . ") 
                       " . $data . "
                GROUP  BY h.order_id 
                ORDER  BY h.created_at desc";
        try {
            $output->writeln($sql);
            $records = $connection->fetchAll($sql);
            if (empty($records)) {
                $this->logger->debug("OMS_ALERT_MESSAGE: No OMS Error data forund from past " . $exitTime . ' Days');
                return;
            }
            $response = [];
            $i = 0;
            foreach ($records as $data) {
                $response[$i]['entity_id'] = $data['entity_id'];
                $response[$i]['order_id'] = $data['order_id'];
                $response[$i]['error_message'] = $data['error_message'];
                $response[$i]['created_at'] = $data['created_at'];
                $response[$i]['created_at'] = $data['created_at'];
                $i++;
            }
            $filename = $brand . '_' . 'OMS_ERROR_' .  date('d-M-Y') . '.csv';
            $stream = $this->directory->openFile('export/' . $filename, 'w+');
            $stream->lock();
            $stream->writeCsv($header);
            foreach ($response as $csvData) {
                $data = [];
                $data[] = $csvData['order_id'];
                $data[] = $csvData['error_message'];
                $data[] = $csvData['created_at'];
                $stream->writeCsv($data);
            }
            $fromAddress = $this->scopeConfig->getValue(self::XML_PATH_OMSERROR_FROMADDRESS);
            $toAddress =  $this->scopeConfig->getValue(self::XML_PATH_OMSERROR_TOADDRESS);
            $subject = $brand . "_OMS Error Alert: " . date('d-M-Y His');
            $bodyContent = "Please find the attached mail";

            $this->sendMail(DirectoryList::VAR_DIR . '/export/', $filename, $fromAddress, $toAddress, $subject, $bodyContent);
            unlink(DirectoryList::VAR_DIR . '/export/' . $filename);

        } catch (\Exception $e) {
            $this->logger->debug("OMS_ALERT_MESSAGE :: Error :: " . $e->getMessage() . " - " . $e->getLine());
            $output->writeln("OMS_ALERT_MESSAGE : Something went worng." . $e->getMessage() . $e->getLine());
        } finally {
            $endTime = microtime(true);
            $output->writeln("Time taken to process OMS_ALERT_MESSAGE data : " . round($endTime - $startTime) . " Seconds ");
            $this->logger->debug("OMS_ALERT_MESSAGE :: Time taken to generate oms alert data : " . round($endTime - $startTime) . ' Seconds ' . "\n");
        }
    }

    /**
     * @throws \Magento\Framework\Exception\MailException
     * @throws \Zend_Mail_Exception
     */
    public function sendEciReport(){
        try{
            if($this->scopeConfig->getValue(self::XML_PATH_REPORT_ORDERS))
            {
                $storeScope = \Magento\Store\Model\ScopeInterface::SCOPE_STORE;
                $brand = $this->scopeConfig->getValue(self::XML_PATH_BRAND_NAME, $storeScope);
                $subject = $brand." - Transaction report : " . date('d-M-Y H:i:s');
                $filePath = DirectoryList::VAR_DIR;
                $fileName = 'OrderTransactionReport'.date('Y-m-d H:i:s').'.csv';
                $bodyContent = "Please Find the Attached mail";
                $fromAddress = $this->scopeConfig->getValue(self::XML_PATH_REPORT_FROMADDRESS);
                $toAddress =  $this->scopeConfig->getValue(self::XML_PATH_REPORT_TOADDRESS);
                $this->sendMail($filePath, $fileName, $fromAddress, $toAddress, $subject, $bodyContent);
            }
        }catch(\Exception $e)
        {
            $output->writeln($e->getMessage());
        }

    }


    /**
     * @throws \Magento\Framework\Exception\MailException
     * @throws \Zend_Mail_Exception
     */
    public function sendEciReportUae($output){
        try{
            if($this->scopeConfig->getValue(self::XML_PATH_REPORT_ORDERS))
            {
                $storeScope = \Magento\Store\Model\ScopeInterface::SCOPE_STORE;
                $brand = $this->scopeConfig->getValue(self::XML_PATH_BRAND_NAME, $storeScope);
                $subject = $brand." - Eci report : " . date('d-M-Y H:i:s');
                $filePath = DirectoryList::VAR_DIR;

                $fileName = 'OrderEciReport'.date('Y-m-d H:i:s').'.csv';
                $bodyContent = "Please Find the Attached mail";
                $fromAddress = $this->scopeConfig->getValue(self::XML_PATH_REPORT_FROMADDRESS);
                $toAddress =  $this->scopeConfig->getValue(self::XML_PATH_ECI_REPORT_FROMADDRESS);
                $this->sendMail($filePath, $fileName, $fromAddress, $toAddress, $subject, $bodyContent);
            }
        }catch(\Exception $e)
        {
            $output->writeln($e->getMessage());
        }


    }

    /**
     * @param $filePath
     * @param $fileNameCustom
     * @param $output
     */
    public function sendOrderCreateReport($filePath, $fileNameCustom, $output)
    {
        try{
            if($this->scopeConfig->getValue(self::XML_PATH_EXPORT_ORDERS))
            {
                $storeScope = \Magento\Store\Model\ScopeInterface::SCOPE_STORE;
                $brand = $this->scopeConfig->getValue(self::XML_PATH_BRAND_NAME, $storeScope);
                $subject = $brand." - Order Create report : " . date('d-M-Y H:i:s');
                $filePath = DirectoryList::VAR_DIR;
                $fileName = $fileNameCustom;
                $bodyContent = "Please Find the Attached mail";
                $fromAddress = 'support@mamasandpapas.ae';
                $toAddress =  $this->scopeConfig->getValue(self::XML_PATH_EXPORT_TOADDRESS);
                $this->sendMail($filePath, $fileName, $fromAddress, $toAddress, $subject, $bodyContent);
            }
        }catch(\Exception $e)
        {
            $output->writeln($e->getMessage());
        }
    }


    /**
     * @param $filePath
     * @param $fileNameCustom
     * @param $output
     */
    public function sendOrderCancelReport($filePath, $fileNameCustom, $output)
    {
        try{
            if($this->scopeConfig->getValue(self::XML_PATH_EXPORT_ORDERS))
            {
                $storeScope = \Magento\Store\Model\ScopeInterface::SCOPE_STORE;
                $brand = $this->scopeConfig->getValue(self::XML_PATH_BRAND_NAME, $storeScope);
                $subject = $brand." - Order Cancel report : " . date('d-M-Y H:i:s');
                $filePath = DirectoryList::VAR_DIR;
                $fileName = $fileNameCustom;
                $bodyContent = "Please Find the Attached mail";
                $fromAddress = 'support@mamasandpapas.ae';
                $toAddress =  $this->scopeConfig->getValue(self::XML_PATH_EXPORT_TOADDRESS);
                $this->sendMail($filePath, $fileName, $fromAddress, $toAddress, $subject, $bodyContent);
            }
        }catch(\Exception $e)
        {
            $output->writeln($e->getMessage());
        }
    }


    /**
     * @param $filePath
     * @param $fileNameCustom
     * @param $output
     */
    public function sendOrderReturnReport($filePath, $fileNameCustom, $output)
    {
        try{
            if($this->scopeConfig->getValue(self::XML_PATH_EXPORT_ORDERS))
            {
                $storeScope = \Magento\Store\Model\ScopeInterface::SCOPE_STORE;
                $brand = $this->scopeConfig->getValue(self::XML_PATH_BRAND_NAME, $storeScope);
                $subject = $brand." - Order Return report : " . date('d-M-Y H:i:s');
                $filePath = DirectoryList::VAR_DIR;
                $fileName = $fileNameCustom;
                $bodyContent = "Please Find the Attached mail";
                $fromAddress = 'support@mamasandpapas.ae';
                $toAddress =  $this->scopeConfig->getValue(self::XML_PATH_EXPORT_TOADDRESS);
                $this->sendMail($filePath, $fileName, $fromAddress, $toAddress, $subject, $bodyContent);
            }
        }catch(\Exception $e)
        {
            $output->writeln($e->getMessage());
        }
    }

    /**
     * @param $output
     * @param $fileName
     */
    public function sendCategoryMissingReport($output, $fileName)
    {
        try{
            if($this->scopeConfig->getValue(self::XML_PATH_MISSING_CATEGORY_EMAIL))
            {
                $storeScope = \Magento\Store\Model\ScopeInterface::SCOPE_STORE;
                $brand = $this->scopeConfig->getValue(self::XML_PATH_BRAND_NAME, $storeScope);
                $subject = $brand." - Missing Report Category : " . date('d-M-Y H:i:s');
                $bodyContent = "Please Find the Attached mail For Category Missing Report";
                $fileName = $fileName.date('Y-m-d H:i:s').'.csv';
                $filePath = DirectoryList::VAR_DIR;
                $fromAddress = 'support@mamasandpapas.ae';
                $toAddress =  $this->scopeConfig->getValue(self::XML_PATH_MISSING_CATEGORY_EMAIL);
                $this->sendMail($filePath, $fileName, $fromAddress, $toAddress, $subject, $bodyContent);
            }
        }catch(\Exception $e)
        {
            $output->writeln($e->getMessage());
        }
    }

    /** This is to send the mail
     * @param $file
     * @throws \Magento\Framework\Exception\MailException
     * @throws \Zend_Mail_Exception
     */
    public function sendMail($filePath, $fileName, $fromAddress, $toAddress, $subject, $bodyContent)
    {
        /**
         * @var Message
         */
        $toAddressArray = explode(",", $toAddress);
        foreach ($toAddressArray as $value) {
            $this->message->addTo($value);
        }
        $this->message->setFrom($fromAddress);
        $this->message->setType(\Zend_Mime::MULTIPART_RELATED);
        $this->message->setSubject($subject);
        $this->message->setBodyHtml($bodyContent);
        if ($filePath != null && $fileName != null) {
            $this->message->createAttachment(
                file_get_contents($filePath."/".$fileName),
                \Zend_Mime::TYPE_OCTETSTREAM,
                \Zend_Mime::DISPOSITION_ATTACHMENT,
                \Zend_Mime::ENCODING_BASE64,
                $fileName);
        }
        $this->transport->sendMessage();
    }





}
