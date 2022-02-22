<?php
/**
 * @author   Mani <kmanidev6@gmail.com>
 * @package Altayer_Support
 * @date 16/08/2020
 * */

namespace Altayer\Support\Model;

use Magento\Framework\App\Filesystem\DirectoryList;
use phpseclib\Net\SFTP;

/**
 * Class AbandonedCart
 * @package Altayer\Support\Model
 */
class AbandonedCart
{
    const XML_PATH_BRAND_NAME = 'general/store_information/name';
    const XML_PATH_SECURE_MEDIA_URL = 'web/secure/base_media_url';
    const XML_PATH_RULE_ID = 'altayer_reminder/reminder_config/rule_id';
    const XML_PATH_REMINDER_THRESHOLD = 'altayer_reminder/reminder_config/reminder_threshold';
    const XML_PATH_EXIT_TIME = 'altayer_reminder/reminder_config/reminder_exit_day';

    const XML_PATH_SFTP_HOST = 'altayer_reminder/reminder_ftp/sftp_host';
    const XML_PATH_SFTP_USER = 'altayer_reminder/reminder_ftp/sftp_user';
    const XML_PATH_SFTP_PASS = 'altayer_reminder/reminder_ftp/sftp_pass';
    const XML_PATH_SFTP_DIR = 'altayer_reminder/reminder_ftp/sftp_directory';

    /**
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
     * AbandonedCart constructor.
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
        \Altayer\Support\Model\AtgReminderFactory $atgReminderFactory
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
    }

    /**
     * @param $output
     */
    public function sendReminderData($output)
    {
        $currencyToCountryMap = array(
            'AED' => 'UAE',
            'KWD' => 'KWT',
            'SAR' => 'KSA',
            'BHD' => 'BHR',
            'OMR' => 'OMR',
            'QAR' => 'QAR'
        );

        $header = [
            'platform', 'Unique Id', 'Checkout Successful', 'Coupon Code', 'Contact Id', 'Customer EmailAddress', 'Customer Salutation',
            'Customer Name', 'Customer Phone', 'Customer Locale', 'Currency', 'Device Id', 'Cart Details'
        ];

        try {
            $startTime = microtime(true);
            @date_default_timezone_set($this->scopeConfig->getValue('general/locale/timezone'));
            //update the reminder if any quote is converted
            $this->updateReminder();
            $storeScope = \Magento\Store\Model\ScopeInterface::SCOPE_STORE;
            $exitTime = "-" . trim($this->scopeConfig->getValue(Self::XML_PATH_EXIT_TIME, $storeScope)) . " days";
            $atgReminder = $this->atgReminderFactory->create()->getCollection()
                ->addFieldToFilter('created_on', ['gteq' => date('Y-m-d', strtotime($exitTime))]);
            $output->writeln($atgReminder->getSelect()->__toString());
            $convertedQuoteId = [];
            if (count($atgReminder) > 0) {
                // creating the quote ID and coupon map array
                $quoteCouponMapArray = [];
                foreach ($atgReminder as $atg) {
                    $quoteCouponMapArray[$atg['quote_id']] = $atg['coupon_code'];
                    if ($atg['checkout_success'] == 'true') {
                        $convertedQuoteId[] = $atg;
                    }
                }
            }
            $connection = $this->_resource->getConnection();
            $quote_item = $connection->getTableName('quote_item');
            $quote_address = $connection->getTableName('quote_address');
            $store = $connection->getTableName('store');
            $atgReminderTable = $connection->getTableName('atg_reminder');
            $this->directory->create('export');
            $brand = $this->scopeConfig->getValue(self::XML_PATH_BRAND_NAME, $storeScope);
            $productMediaPath = $this->scopeConfig->getValue(Self::XML_PATH_SECURE_MEDIA_URL, $storeScope);
            $threshold = "-" . trim($this->scopeConfig->getValue(Self::XML_PATH_REMINDER_THRESHOLD, $storeScope)) . " hours";
            foreach ($currencyToCountryMap as $currency => $country) {
                $quotes = $this->quoteFactory->create()
                    ->getCollection()
                    ->addFieldToFilter('main_table.is_active', 1)
                    ->addFieldToFilter('main_table.items_count', ['gt' => 0])
                    ->addFieldToFilter('main_table.customer_id', ['neq' => null])
                    ->addFieldToFilter('main_table.base_currency_code', ['eq' => $currency])
                    ->addFieldToFilter('main_table.updated_at', ['lteq' => date('Y-m-d H:i:s', strtotime($threshold))])
                    ->addFieldToFilter('main_table.updated_at', ['gteq' => date('Y-m-d', strtotime($exitTime))])
                    ->setOrder('main_table.base_currency_code', \Magento\Framework\Data\Collection::SORT_ORDER_DESC)
                    ->setOrder('main_table.updated_at', \Magento\Framework\Data\Collection::SORT_ORDER_DESC);
                $quotes->getSelect()
                    ->joinLeft(array('qi' => $quote_item), 'main_table.entity_id = qi.quote_id', ['product_id' => new \Zend_Db_Expr('group_concat(DISTINCT qi.product_id SEPARATOR ",")')]);
                $quotes->getSelect()
                    ->joinLeft(array('qa' => $quote_address), 'main_table.entity_id = qa.quote_id', ['telephone']);
                $quotes->getSelect()
                    ->joinLeft(array('st' => $store), 'main_table.store_id = st.store_id', ['code as store_code']);
                $quotes->getSelect()->group('main_table.entity_id');
                $output->writeln($quotes->getSelect()->__toString());

                $this->logger->debug("Altayer_Reminder :: Total number of abandoned cart for country " . $country . ' - ' . count($quotes));

                if (count($quotes) > 0) {
                    $response = [];
                    $i = 0;
                    foreach ($quotes->getData() as $data) {
                        if (empty($data['telephone'])) {
                            continue;
                        }
                        $response[$i]['quoteId'] = $data['entity_id'];
                        // Checking if Coupon Code is Already exist in our database if not they we will generate the coupon and send
                        if (count($atgReminder) > 0) {
                            if (array_key_exists($data['entity_id'], $quoteCouponMapArray)) {
                                $response[$i]['couponCode'] = $quoteCouponMapArray[$data['entity_id']];
                            } else {
                                $response[$i]['couponCode'] = $this->generateCouponCode($this->scopeConfig->getValue(self::XML_PATH_RULE_ID));
                            }
                        } else {
                            $response[$i]['couponCode'] = $this->generateCouponCode($this->scopeConfig->getValue(self::XML_PATH_RULE_ID));
                        }
                        $response[$i]['customerEmail'] = $data['customer_email'];
                        $response[$i]['title'] = $data['customer_prefix'];
                        $response[$i]['customerName'] = $data['customer_firstname'] . ' ' . $data['customer_lastname'];
                        $response[$i]['telephone'] = $data['telephone'];
                        $response[$i]['storeCode'] = $data['store_code'];
                        $response[$i]['currency'] = $data['base_currency_code'];
                        $productIds = explode(',', $data['product_id']);
                        $j = 0;
                        foreach ($productIds as $productId) {
                            $product = $this->productFactory->create()->load($productId);
                            $response[$i]['cartDetails'][$j]['itemBrand'] = $this->getOptionValue($product, "brand");
                            $response[$i]['cartDetails'][$j]['itemName'] = $product->getName();
                            $response[$i]['cartDetails'][$j]['itemQuantity'] = $data['items_qty'];
                            $response[$i]['cartDetails'][$j]['itemImageUrl'] = $productMediaPath . 'catalog/product' . $product->getImage();
                            $response[$i]['cartDetails'][$j]['itemPrice'] = $product->getSpecialPrice();
                            $response[$i]['cartDetails'][$j]['itemRegularPrice'] = $product->getPrice();
                            $j++;
                        }
                        $i++;
                    }

                    // Creating the CSV for the Quote Data
                    //$filename = $brand . '_' . date("His") . '_' . $country . '.csv';
                    $filename = $brand . '_' . $country . '.csv';
                    $stream = $this->directory->openFile('export/' . $filename, 'w+');
                    $stream->lock();
                    $stream->writeCsv($header);
                    foreach ($response as $csvData) {
                        $data = [];
                        $data[] = "web"; //platform
                        $data[] = $csvData['quoteId']; //unique Id
                        $data[] = "false"; //Checkout Successful
                        $data[] = $csvData['couponCode'];

                        $data[] = " "; //Contact Id
                        $data[] = $csvData['customerEmail'];
                        $data[] = $csvData['title'];
                        $data[] = $csvData['customerName'];
                        $data[] = $csvData['telephone'];
                        $data[] = $csvData['storeCode'];
                        $data[] = $csvData['currency'];
                        $data[] = " "; // DeviceId
                        $data[] = json_encode($csvData['cartDetails']);
                        $stream->writeCsv($data);
                    }
                    // add the converted order to the csv as well
                    foreach ($convertedQuoteId as $order) {
                        if ($order['currency'] == $currency) {
                            $data = [];
                            $data[] = "web"; //platform
                            $data[] = $order['quote_id']; //unique Id
                            $data[] = "true"; //Checkout Successful
                            $data[] = $order['coupon_code'];
                            $data[] = " "; //Contact Id
                            $data[] = $order['customer_email'];
                            $data[] = $order['customer_title'];
                            $data[] = $order['customer_name'];
                            $data[] = $order['customer_phone'];
                            $data[] = $order['customer_locale'];
                            $data[] = $order['currency'];
                            $data[] = " "; // DeviceId
                            $data[] = $order['cart_details'];
                            $stream->writeCsv($data);
                        }
                    }
                    // Uploading the CSV to the SFTP Location
                    $host = $this->scopeConfig->getValue(self::XML_PATH_SFTP_HOST);
                    $user = $this->scopeConfig->getValue(self::XML_PATH_SFTP_USER);
                    $password = $this->scopeConfig->getValue(self::XML_PATH_SFTP_PASS);
                    $dir = $this->scopeConfig->getValue(self::XML_PATH_SFTP_DIR);
                    $sftp = new SFTP($host);
                    if (!$sftp->login($user, $password)) {
                        $this->logger->debug("Altayer_Reminder :: Not able to login to Server ");
                        $output->writeln("Not able to login to Server");
                        return;
                    }
                    if ($sftp->put($dir . $filename, DirectoryList::VAR_DIR . '/export/' . $filename, SFTP::SOURCE_LOCAL_FILE)) {
                        $this->logger->debug("Altayer_Reminder :: File Uploaded Successfully ");
                        $output->writeln($filename . " File Uploaded Successfully");
                        // Preparing the atg_reminder data to insert in our table
                        $atgReminderArray = [];
                        foreach ($response as $reminderData) {
                            $atgReminderArray [] = [
                                'quote_id' => $reminderData['quoteId'],
                                'coupon_code' => $reminderData['couponCode'],
                                'customer_email' => $reminderData['customerEmail'],
                                'customer_title' => $reminderData['title'],
                                'customer_name' => $reminderData['customerName'],
                                'customer_phone' => $reminderData['telephone'],
                                'customer_locale' => $reminderData['storeCode'],
                                'currency' => $reminderData['currency'],
                                'cart_details' => json_encode($reminderData['cartDetails'])
                            ];
                        }
                        // inserting the data `atg_reminder`
                        try {
                            $connection->beginTransaction();
                            if (!empty($atgReminderArray)) {
                                $connection->insertOnDuplicate($atgReminderTable, $atgReminderArray);
                            }
                            $connection->commit();
                            $this->logger->debug("Altayer_Reminder :: Updated atg_reminder table ");
                        } catch (Exception $e) {
                            if (isset($this->_connection)) {
                                $this->_connection->rollBack();
                                $this->logger->debug("Altayer_Reminder :: Transaction Rollback ");
                            }
                        }
                        // deleteing the csv file from the server after all process
                        unlink(DirectoryList::VAR_DIR . '/export/' . $filename);
                    } else {
                        $this->logger->debug("Altayer_Reminder :: Error uploading file ");
                        $output->writeln($filename . " File is not Uploaded");
                    }
                }
            }
        } catch (\Exception $e) {
            $this->logger->debug("Altayer Reminder :: Error :: " . $e->getMessage() . " - " . $e->getLine());
            $output->writeln("Something went worng." . $e->getMessage() . $e->getLine());
        } finally {
            $endTime = microtime(true);
            $output->writeln("Time taken to process reminder data : " . round($endTime - $startTime) . " Seconds ");
            $this->logger->debug("Altayer_Reminder :: Time taken to process reminder data : " . round($endTime - $startTime) . ' Seconds ' . "\n");
        }
    }

    /**
     * @param $ruleId
     * @return false|mixed
     */
    protected function generateCouponCode($ruleId)
    {
        $ruleModel = $this->ruleFactory->create();
        $rule = $ruleModel->load($ruleId);
        try {
            if ($rule->getId()) {
                $data = array(
                    'rule_id' => $ruleId,
                    'qty' => 1,
                    'length' => '12',
                    'format' => 'alphanum',
                    'prefix' => 'CART-',
                    'suffix' => '',
                    'dash' => 0,
                    'uses_per_coupon' => 1
                );
                $generator = $this->massgenerator;
                if (!$generator->validateData($data)) {
                    return false;
                } else {
                    $generator->setData($data);
                    $generator->generatePool();
                    $generated = $generator->getGeneratedCount();
                    $codes = $generator->getGeneratedCodes();
                    $this->logger->debug("Coupon Code : " . $codes[0]);
                    return $codes[0];
                }
            } else {
                $this->logger->debug("Altayer_Reminder :: No Such Rule Find with the Rule Id :" . $ruleId);
                return false;
            }
        } catch (\Magento\Framework\Exception\LocalizedException $e) {
            $this->logger->critical($e);
            return false;
        } catch (\Exception $e) {
            $this->logger->critical($e);
            return false;
        }
    }

    /**
     *
     */
    public function updateReminder()
    {
        try {
            $sql = "UPDATE atg_reminder t1 INNER JOIN sales_order t2 ON t1.quote_Id = t2.quote_id
                    SET t1.order_id = t2.increment_id , t1.order_placed_on_device = t2.device , 
                    t1.order_created_on = t2.created_at , t1.applied_coupon_code_on_order = t2.coupon_code,
                    t1.order_id = 'true' where t1.order_id is null;";
            $data = $this->_resource->getConnection()->query($sql);
        } catch (\Exception $e) {
            $this->_logger->debug("Altayer_Reminder :: Error updating atg_reminder table " . $e->getMessage());
            throw new \Exception($e);
        }
        return $data;
    }

    /**
     * @param $product
     * @param $property
     * @return string
     */
    protected function getOptionValue($product, $property)
    {
        $optionText = "";
        try {
            $optionId = $product->getData($property);
            $attr = $product->getResource()->getAttribute($property);
            if (!empty($attr) && $attr->usesSource()) {
                $optionText = $attr->getSource()->getOptionText($optionId);
            }
        } catch (\Exception $e) {
            $this->logger->debug("Error while getItemValue " . $property);
        }
        return $optionText;
    }
}
