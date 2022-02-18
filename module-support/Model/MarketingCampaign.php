<?php
/**
 * @author Amrendra Singh <amrendragr8@gmail.com>
 * @package Altayer_Support
 * */

namespace Altayer\Support\Model;

use Magento\Framework\App\Filesystem\DirectoryList;
use phpseclib\Net\SFTP;

class MarketingCampaign
{
    const XML_PATH_BRAND_NAME = 'general/store_information/name';
    const XML_PATH_MARKETING_RULE_ID = 'altayer_order_monitor/marketing_campaign/rule_id';
    const XML_PATH_SFTP_HOST = 'altayer_order_monitor/marketing_campaign/sftp_host';
    const XML_PATH_SFTP_USER = 'altayer_order_monitor/marketing_campaign/sftp_user';
    const XML_PATH_SFTP_PASS = 'altayer_order_monitor/marketing_campaign/sftp_pass';
    const XML_PATH_SFTP_DIR = 'altayer_order_monitor/marketing_campaign/sftp_directory';
    const XML_PATH_SECURE_MEDIA_URL = 'web/secure/base_media_url';

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
     * @var \Altayer\Support\Model\AtgMarketing
     */
    protected $atgMarketingFactory;

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
        \Altayer\Support\Model\AtgMarketingFactory $atgMarketingFactory
    ) {
        $this->logger = $logger;
        $this->quoteFactory = $quoteFactory;
        $this->productFactory = $productFactory;
        $this->_resource = $resource;
        $this->config = $config;
        $this->imageHelperFactory = $imageHelperFactory;
        $this->directory = $filesystem->getDirectoryWrite(DirectoryList::VAR_DIR);
        $this->scopeConfig = $scopeConfig;
        $this->ruleFactory= $ruleFactory;
        $this->massgenerator = $massgenerator;
        $this->atgMarketingFactory = $atgMarketingFactory;
    }

    public function getCampiagnData($output)
    {
        $currencyToCountryMap = array(
            'AED' => 'UAE',
            'KWD' => 'KWT',
            'SAR' => 'KSA',
            'BHD' => 'BHR',
            'OMR' => 'OMR',
            'QAR' => 'QAR'
        );
        try{
            /**
             * Fetching the Record from the atg_marketing table
             */
            $atgMarketing = $this->atgMarketingFactory->create()->getCollection()
                ->addFieldToSelect('quote_id')
                ->addFieldToSelect('coupon_code')
                ->addFieldToFilter('created_on', ['gteq' => date('Y-m-d', strtotime('-3 days'))]);
            if (count($atgMarketing) > 0){
                // creating the quote ID and coupon map array
                $quoteCouponMapArray = [];
                foreach ($atgMarketing as $atg){
                    $quoteCouponMapArray[$atg['quote_id']] = $atg['coupon_code'];
                }
            }
            $connection = $this->_resource->getConnection();
            $quote_item = $connection->getTableName('quote_item');
            $quote_address = $connection->getTableName('quote_address');
            $store = $connection->getTableName('store');
            $atgMarketingTable = $connection->getTableName('atg_marketing');
            $this->directory->create('export');
            $header = ['quoteId', 'Coupon Code', 'Customer EmailAddress','Title' ,'Customer Name','Customer Phone', 'Customer Locale', 'Currency', 'Cart Details'];
            $storeScope = \Magento\Store\Model\ScopeInterface::SCOPE_STORE;
            $brand = $this->scopeConfig->getValue(self::XML_PATH_BRAND_NAME, $storeScope);
            $productMediaPath = $this->scopeConfig->getValue(Self::XML_PATH_SECURE_MEDIA_URL,$storeScope);
            foreach ($currencyToCountryMap as $currency => $country){
                $quotes = $this->quoteFactory->create()
                    ->getCollection()
                    ->addFieldToFilter('main_table.is_active', 1)
                    ->addFieldToFilter('items_count', ['gt' => 0])
                    ->addFieldToFilter('main_table.customer_id', ['neq' => null])
                    ->addFieldToFilter('base_currency_code', ['eq' => $currency])
                    ->setOrder('base_currency_code', \Magento\Framework\Data\Collection::SORT_ORDER_DESC)
                    ->setOrder('updated_at', \Magento\Framework\Data\Collection::SORT_ORDER_DESC);
                $quotes->getSelect()
                    ->joinLeft(array('qi' => $quote_item),'main_table.entity_id = qi.quote_id',['product_id' => new \Zend_Db_Expr('group_concat(DISTINCT qi.product_id SEPARATOR ",")')]);
                $quotes->getSelect()
                    ->joinLeft(array('qa' => $quote_address),'main_table.entity_id = qa.quote_id',['telephone']);
                $quotes->getSelect()
                    ->joinLeft(array('st' => $store), 'main_table.store_id = st.store_id', ['code as store_code']);
                $quotes->getSelect()->group('main_table.entity_id');
                if (count($quotes) > 0){
                    $response = [];
                    $i = 0;
                    foreach ($quotes->getData() as $data){
                        if (empty($data['telephone'])){
                            continue;
                        }
                        $response[$i]['quoteId'] = $data['entity_id'];
                        // Checking if Coupon Code is Already exist in our database if not they we will generate the coupon and send
                        if (count($atgMarketing) > 0){
                            if (array_key_exists($data['entity_id'], $quoteCouponMapArray)){
                                $response[$i]['couponCode'] = $quoteCouponMapArray[$data['entity_id']];
                            }else{
                                $response[$i]['couponCode'] = $this->generateMarketingCampaignCouponCode($this->scopeConfig->getValue(self::XML_PATH_MARKETING_RULE_ID));
                            }
                        }else{
                            $response[$i]['couponCode'] = $this->generateMarketingCampaignCouponCode($this->scopeConfig->getValue(self::XML_PATH_MARKETING_RULE_ID));
                        }
                        $response[$i]['customerEmail'] = $data['customer_email'];
                        $response[$i]['title'] = $data['customer_prefix'];
                        $response[$i]['customerName'] = $data['customer_firstname'].' '. $data['customer_lastname'];
                        $response[$i]['telephone'] = $data['telephone'];
                        $response[$i]['storeCode'] = $data['store_code'];
                        $response[$i]['currency'] = $data['base_currency_code'];
                        $productIds = explode(',', $data['product_id']);
                        $j = 0;
                        foreach ($productIds as $productId){
                            $product = $this->productFactory->create()->load($productId);
                            $response[$i]['cartDetails'][$j]['itemBrand'] = $this->getOptionValue($product,"brand");
                            $response[$i]['cartDetails'][$j]['itemName'] = $product->getName();
                            $response[$i]['cartDetails'][$j]['itemQuantity'] = $data['items_qty'];
                            $response[$i]['cartDetails'][$j]['itemImageUrl'] = $productMediaPath.'catalog/product'. $product->getImage();
                            $response[$i]['cartDetails'][$j]['itemPrice'] = $product->getSpecialPrice();
                            $response[$i]['cartDetails'][$j]['itemRegularPrice'] = $product->getPrice();
                            $j++;
                        }
                        $i++;
                    }

                    // Creating the CSV for the Quote Data
                    $filename = $brand.'_'.time().'_'.$country.'.csv';
                    $stream = $this->directory->openFile('export/'.$filename, 'w+');
                    $stream->lock();
                    $stream->writeCsv($header);
                    foreach ($response as $csvData){
                        $data = [];
                        $data[] = $csvData['quoteId'];
                        $data[] = $csvData['couponCode'];
                        $data[] = $csvData['customerEmail'];
                        $data[] = $csvData['title'];
                        $data[] = $csvData['customerName'];
                        $data[] = $csvData['telephone'];
                        $data[] = $csvData['storeCode'];
                        $data[] = $csvData['currency'];
                        $data[] = json_encode($csvData['cartDetails']);
                        $stream->writeCsv($data);
                    }

                    // Uploading the CSV to the SFTP Location
                    $host = $this->scopeConfig->getValue(self::XML_PATH_SFTP_HOST);
                    $user = $this->scopeConfig->getValue(self::XML_PATH_SFTP_USER);
                    $password = $this->scopeConfig->getValue(self::XML_PATH_SFTP_PASS);
                    $dir = $this->scopeConfig->getValue(self::XML_PATH_SFTP_DIR);
                    $sftp = new SFTP($host);
                    if (!$sftp->login($user, $password)) {
                        $output->writeln("Not able to login to Server");
                        return;
                    }
                    if($sftp->put($dir.$filename, DirectoryList::VAR_DIR . '/export/' . $filename, SFTP::SOURCE_LOCAL_FILE)){
                        $output->writeln($filename." File Uploaded Successfully");
                        // Preparing the atg_marketing data to insert in our table
                        $atgMarketingArray = [];
                        foreach ($response as $marketingData){
                            $atgMarketingArray [] = [
                                'quote_id' => $marketingData['quoteId'],
                                'coupon_code' => $marketingData['couponCode'],
                                'customer_email' => $marketingData['customerEmail'],
                                'customer_title' => $marketingData['title'],
                                'customer_name' => $marketingData['customerName'],
                                'customer_phone' => $marketingData['telephone'],
                                'customer_locale' => $marketingData['storeCode'],
                                'currency' => $marketingData['currency'],
                                'cart_details' => json_encode($marketingData['cartDetails'])
                            ];
                        }
                        // inserting the data to our table `atg_marketing`
                        try {
                            $connection->beginTransaction();
                            if (!empty($atgMarketingArray)){
                                $connection->insertOnDuplicate($atgMarketingTable, $atgMarketingArray);
                            }
                            $connection->commit();
                        }catch (Exception $e){
                            if (isset($this->_connection)) {
                                $this->_connection->rollBack();
                            }
                        }
                        // deleteing the csv file from the server after all process
                        unlink(DirectoryList::VAR_DIR . '/export/' . $filename);
                    }else{
                        $output->writeln($filename." File is not Uploaded");
                    }
                }
            }
        }catch (\Exception $e){
            $this->logger->debug("Marketing Campaign :: Error :: ". $e->getMessage());
            $output->writeln("Something went worng.");
        }
    }

    protected function generateMarketingCampaignCouponCode($ruleId)
    {
        $ruleModel = $this->ruleFactory->create();
        $rule = $ruleModel->load($ruleId);
        try{
            if($rule->getId())
            {
                $data = array(
                    'rule_id'   => $ruleId,
                    'qty'       => 1,
                    'length'    => '12',
                    'format'    => 'alphanum',
                    'prefix'    => '',
                    'suffix'    => '',
                    'dash'      => 0,
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
                    $this->logger->debug("Marketing Campaign Code : ".$codes[0]);
                    return $codes[0];
                }
            }else{
                $this->logger->debug("No Such Rule Find with the Rule Id :". $ruleId);
                return false;
            }
        }catch (\Magento\Framework\Exception\LocalizedException $e) {
            $this->logger->critical($e);
            return false;
        }catch (\Exception $e){
            $this->logger->critical($e);
            return false;
        }
    }

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