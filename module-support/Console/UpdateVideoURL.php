<?php

/**
 *
 */

namespace Altayer\Support\Console;

use Altayer\GapIntegration\Api\ProductMessageInterface;
use Altayer\Integration\Model\RMSItem;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\RequestOptions;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\MessageQueue\PublisherInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Magento\Framework\App\ResourceConnection;
use Altayer\Sales\Helper\SlackApiHelper;
use Psr\Log\LoggerInterface as Logger;
use function GuzzleHttp\Psr7\stream_for;

/**
 * Class UpdateVideoURL
 * @package Altayer\Support\Console\Command
 */
class UpdateVideoURL extends Command
{

    const GAP_API_RETRY_COUNT = 'api_configuration/general/api_retry_count';
    const GAP_API_ENDPOINT = 'api_configuration/general/api_endpoint';
    const GAP_API_FALLBACK_ENDPOINT = 'api_configuration/general/api_fallback_endpoint';
    const ALTAYER_VENDOR_ID = 'api_configuration/general/vendor_id';
    const MAIN_IMAGE_SUFFIX_URL = 'altayer_integration/general/main_image_suffix';
    const SWATCH_IMAGE_SUFFIX_URL = 'altayer_integration/general/swatch_image_suffix';
    const API_IMAGES_DIR = 'api_configuration/general/api_images_dir';
    const API_VIDEOS_CDN_DIR = 'api_configuration/general/api_videos_dir';
    const GAP_CATALOG_API_URL = 'api_configuration/general/catalog_api_url';
    const GAP_CATALOG_API_KEY = 'api_configuration/general/catalog_api_key';
    const GAP_CATALOG_API_BRAND_AND_MARKET = 'api_configuration/general/catalog_api_brand_and_market';
    const GAP_CATALOG_API_PAGE_SIZE = 'api_configuration/general/catalog_api_page_size';

    const PRODUCT_STATUS_OFFLINE = 'OFFLINE';
    const PRODUCT_STATUS_IN_PROGRESS = 'IN PROGRESS';
    const PRODUCT_STATUS_SUCCESS_STUDIO = 'SUCCESS (STUDIO)';

    protected $MAIN_IMAGE_SUFFIX;
    protected $SWATCH_IMAGE_SUFFIX;
    protected $RETRY_COUNT;
    protected $imagesDirectory;
    protected $videosDirectory;
    protected $cachedResponse = [];
    protected $altayerImageMapping = ['in' => ['Z', 'VLI', 'AV2_VLI', 'AV2_Z'],
        'fr' => ['AV3_Z', 'AV3_VLI'],
        'bk' => ['AV1_Z', 'AV1_VLI', 'AV1', 'AV1_Z'],
        'cu' => ['AV4_Z', 'AV4_VLI', 'AV3_VLI'],
        'pk' => ['AV5_Z', 'AV5_VLI', 'AV2_Z', 'AV2_QL'],
        'sw' => ['S']];
    /* 'e1' => ['QL'], 'e2' => ['Z'], 'ou' => ['SI']*/
    /**
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    protected $altayerVideoMapping = ['vd' => ['AV9_VD']];

    protected $_scopeConfig;
    /**
     * @var Logger
     */
    protected $_logger;
    /**
     * @var \Magento\Framework\App\Filesystem\DirectoryList
     */
    protected $_directoryList;

    /**
     * @var PublisherInterface
     */
    protected $_publisher;

    /**
     * @var ProductMessageInterface
     */
    protected $_productMessage;

    protected $_dropboxHelper;

    protected $connection;

    protected function configure()
    {
        $this->setName('fix:video')
            ->setDescription('This will pull video');
        parent::configure();
    }


    public function __construct(
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        Logger $logger,
        \Magento\Framework\App\Filesystem\DirectoryList $directoryList,
        PublisherInterface $publisher,
        RMSItem $connection,
        $name = null
    )
    {
        parent::__construct($name);
        $this->_scopeConfig = $scopeConfig;
        $this->_logger = $logger;
        $this->_directoryList = $directoryList;
        $this->_publisher = $publisher;
        $this->connection = $connection->getCollection()->getConnection();
        $this->RETRY_COUNT = $scopeConfig->getValue(self::GAP_API_RETRY_COUNT);
        $this->MAIN_IMAGE_SUFFIX = $scopeConfig->getValue(self::MAIN_IMAGE_SUFFIX_URL);
        $this->imagesDirectory = $scopeConfig->getValue(self::API_IMAGES_DIR);
        $this->videosDirectory = $scopeConfig->getValue(self::API_VIDEOS_CDN_DIR);
        $this->SWATCH_IMAGE_SUFFIX = "sw";
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|void|null
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        try {
            //get products
            $dataList = $this->getVPNData();
            foreach ($dataList as $data) {
                $this->_logger->info(" setting details for vpn and color code " . $data["vpn"] . " <--> " . $data["color_code"]);
                if ($data["vpn"] === '203534' and $data["color_code"] === '02') {
                    echo '';
                }
                $this->fetchProductDetails($data["vpn"], $data["color_code"]);
            }
            //$this->fetchProductDetails("546930", "00");
        } catch (\Exception $e) {
            $this->_logger->debug("Order Monitoring: Error" . $e->getMessage() . ' :: ' . $e->getFile() . ' :: ' . $e->getLine());
        }
    }

    /**
     * Consume message and fetch and save product data.
     *
     * @param ProductMessageInterface $msg
     */
    public function fetchProductDetails($vpn, $color_code)
    {
        $businessCatalogItemId = $vpn . $color_code;
        $productData = $this->getProductData($vpn, $color_code);
        try {
            if (!empty($productData)) {
                $remark = $this->saveProductData($productData, $vpn, $color_code);
            } else {
              
                $this->updateVPNData("", "", "failed", $vpn, $color_code);
            }
        } catch (\Exception $ex) {
            $this->_logger->info("fetchProductDetails :Error : " . $ex->getMessage() . " (" . $ex->getLine());
        }
    }

    /**
     * @param $productData
     * @return bool|string|null
     */
    public function getSourceLocation($productData)
    {
        $productStyle = $productData->productStyleV1;
        if (empty($productStyle)) {
            return null;
        }
        return substr($productData->resourceUrl, 0, strpos($productData->resourceUrl, 'resources'));
    }

    /**
     * Method to retry on gap.eu if items are not found on gap.com
     * @param $vpn
     * @param $color_code
     * @return mixed|null|void
     */
    public function getProductData($vpn, $color_code)
    {
        //Check data from Com
        $businessCatalogItemId = $this->getComBusinessCatalogItemId($vpn, $color_code);
        $productData = $this->fetchProductData($businessCatalogItemId, $this->getGapUrl());
        //check data from Europe
        if ($productData == null) {
            $businessCatalogItemId = $this->getEUBusinessCatalogItemId($vpn, $color_code);
            $productData = $this->fetchProductData($businessCatalogItemId, $this->getGapEuUrl());
        }
        //check data from  canada
        if ($productData == null) {
            $businessCatalogItemId = $this->getCanadaBusinessCatalogItemId($vpn, $color_code);
            $productData = $this->fetchProductData($businessCatalogItemId, $this->getGapCanadaUrl());
        }

        //check data from japan just get only the image
        if ($productData == null) {
            $businessCatalogItemId = $this->getJapanBusinessCatalogItemId($vpn, $color_code);
            $productData = $this->fetchProductData($businessCatalogItemId, $this->getGapJapanUrl());
        }
        return $productData;
    }

    /**
     * Method returns product collection
     * @return \Magento\Catalog\Model\ResourceModel\Product\Collection
     */
    public function getProductCollection()
    {
        /** @var $collection \Magento\Catalog\Model\ResourceModel\Product\Collection */
        $collection = $this->_productCollectionFactory->create();
        return $collection;
    }

    /**
     * @param $skuId
     * @return mixed
     */
    private function getUDADetailsBySKUId($skuId, $uda_key)
    {
        $udaCollection = $this->_udaCollectionFactory->create()
            ->addFieldToSelect('*')
            ->addFieldToFilter('sku', $skuId);

        foreach ($udaCollection as $udaItem) {
            $key = $udaItem->getData("uda_key");
            if ($uda_key == $key) {
                $uda_value = trim($udaItem->getData("uda_value"));
                break;
            }
        }
        return $uda_value;
    }


    /**
     * @param null $resourceUrl
     * @param null $imageUrl
     * @param $imageName
     * @param $dropboxSyncStatus
     * @param float $timeout
     * @param bool $convertJpg
     * @return null|string
     */
    public function downloadImage($resourceUrl = null, $imageUrl = null, $imageName, $dropboxSyncStatus, $timeout = 30.0, $convertJpg = true)
    {
        if ($this->_dropboxHelper->enable()) {
            $uploadStatus = $this->uploadToDropbox($resourceUrl, $imageUrl, $imageName, $dropboxSyncStatus);
            if ($uploadStatus) {
                //if sucessfully uploaded , do not download to disk
                return $imageName;
            }

        }
        if ($resourceUrl == null || $imageUrl == null || empty($this->imagesDirectory)) {
            $this->_logger->addError("Invalid input");
            return null;
        }
        $importDir = dirname($this->imagesDirectory);
        $fileName = $this->imagesDirectory . "/" . $imageName;
        $resource = fopen($fileName, 'w');
        $url = $resourceUrl . $imageUrl;
        $stream = stream_for($resource);
        $client = new Client();
        $options = [
            RequestOptions::SINK => $stream, // the body of a response
            RequestOptions::CONNECT_TIMEOUT => 20.0,    // request
            RequestOptions::TIMEOUT => $timeout,    // response
            RequestOptions::VERIFY => false
        ];
        $response = $client->request('GET', $url, $options);
        $stream->close();
        if ($response->getStatusCode() === 200) {
            if ($convertJpg)
                imagejpeg(imagecreatefromjpeg($fileName), $fileName);
            return $fileName;
        }
        return null;
    }

    /**
     * @param $productData
     * @param $fallbackProductData
     * @return mixed
     */
    protected function mergeFallbackApiData($productData, $fallbackProductData)
    {
        if (empty($productData)) {
            return $fallbackProductData;
        } else {
            $productData->productStyleV1 = $fallbackProductData->productStyleV1;
        }

        return $productData;
    }


    /**
     * @param $productData
     * @param $productId
     * @param $apiFallbackUrl
     * @return mixed
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    protected function applyFallbackApiData($productData, $productId, $apiFallbackUrl)
    {
        $client = new Client();

        try {
            $this->_logger->info("Sending fallback request - $apiFallbackUrl with parameters " . $productId);
            $apiFallbackUrl .= '/' . $productId;
            $request = new Request('GET', $apiFallbackUrl);
            $response = $client->send($request, ['timeout' => 20]);
            $fallbackProductData = json_decode($response->getBody()->getContents());

            if (empty($fallbackProductData)) {
                $this->_logger->info("Fallback response empty for  - $productId");

                return $productData;
            }

            return $this->mergeFallbackApiData($productData, $fallbackProductData);
        } catch (\Exception $e) {
            $this->_logger->info("Exception while product data fetch : {$e->getMessage()}");

            return $productData;
        }
    }

    /**
     * @param $productData
     * @return bool
     */
    protected function isProductDataFull($productData)
    {
        return $productData && isset($productData->productStyleV1);
    }

    /**
     * @param $productId
     * @param $apiUrl
     * @param $apiFallbackUrl
     * @return mixed|null|void
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function fetchProductData($productId = null, $apiUrl, $apiFallbackUrl = '')
    {
        $vendorKey = $this->_scopeConfig->getValue(self::ALTAYER_VENDOR_ID);

        if (empty($productId)) {
            $this->_logger->info("Invalid parameters getProductData: ");
            return;
        }
        $client = new Client();
        try {
            $apiUrl .= '/' . $productId . '?appId=' . $vendorKey . '&isEffectiveDateAware=false';
            $this->_logger->info("Sending request - $apiUrl with parameters " . $productId);
            $response = $client->request('GET', $apiUrl, ['timeout' => 20]);
            $productData = json_decode($response->getBody()->getContents());
            if (!empty($productData))
                $this->_logger->info("Response success  from  - " . $apiUrl);
            return $productData;
        } catch (\Exception $e) {
            $this->_logger->info("Exception while product data fetch : " . $e->getMessage());
            return null;
        }
    }

    /**
     * gap.eu the format is 000+StyleCode+0+ColorCode.
     * @param $vpn
     * @param $color_code
     * @return string
     */
    public function getEUBusinessCatalogItemId($vpn, $color_code)
    {
        return '000' . $vpn . '0' . $color_code;
    }

    /**
     *For gap.com format is StyleCode+ColorCode+3 .
     * @param $vpn
     * @param $color_code
     * @return string
     */
    public function getCanadaBusinessCatalogItemId($vpn, $color_code)
    {
        return $vpn . $color_code . '3';
    }

    /**
     *For gap.com format is StyleCode+ColorCode+2 .
     * @param $vpn
     * @param $color_code
     * @return string
     */
    public function getComBusinessCatalogItemId($vpn, $color_code)
    {
        return $vpn . $color_code . '2';
    }

    /**
     *For gapfactory.com format is StyleCode+ColorCode+1 .
     * @param $vpn
     * @param $color_code
     * @return string
     */
    public function getFactoryBusinessCatalogItemId($vpn, $color_code)
    {
        return $vpn . $color_code . '1';
    }

    /**
     *For gapfactory.com format is StyleCode+ColorCode+1 .
     * @param $vpn
     * @param $color_code
     * @return string
     */
    public function getJapanBusinessCatalogItemId($vpn, $color_code)
    {
        return $vpn . $color_code . '6';
    }

    /**
     * returns gap.com url
     */
    public function getGapUrl()
    {
        return $this->_scopeConfig->getValue(self::GAP_API_ENDPOINT);
    }

    /**
     * returns www.gapfactory.com url
     */
    public function getGapFallbackUrl()
    {
        return $this->_scopeConfig->getValue(self::GAP_API_FALLBACK_ENDPOINT);
    }

    /**
     * returns gap.eu url
     */
    public function getGapEuUrl()
    {
        $apiUrl = $this->_scopeConfig->getValue(self::GAP_API_ENDPOINT);
        return str_replace("gap.com", "gap.eu", $apiUrl);
    }

    /**
     * returns gap.eu url
     */
    public function getGapCanadaUrl()
    {
        $apiUrl = $this->_scopeConfig->getValue(self::GAP_API_ENDPOINT);
        return str_replace("gap.com", "gapcanada.ca", $apiUrl);
    }

    /**
     * returns gap.eu url
     */
    public function getGapFactoryUrl()
    {
        $apiUrl = $this->_scopeConfig->getValue(self::GAP_API_ENDPOINT);
        return str_replace("gap.com", "gapfactory.com", $apiUrl);
    }

    /**
     * returns gap.eu url
     */
    public function getGapJapanUrl()
    {
        $apiUrl = $this->_scopeConfig->getValue(self::GAP_API_ENDPOINT);
        return str_replace("gap.com", "gap.co.jp", $apiUrl);
    }


    /**
     * Method returns both possible business catalog item id.
     * @param $vpn
     * @param $color_code
     * @return array
     */
    public function getAllProductBusinessIds($vpn, $color_code,$factory_vpn, $factory_color_code)
    {
        return [
            $this->getFactoryBusinessCatalogItemId($factory_vpn, $factory_color_code),
            $this->getComBusinessCatalogItemId($vpn, $color_code),
            $this->getEUBusinessCatalogItemId($vpn, $color_code),
            $this->getCanadaBusinessCatalogItemId($vpn, $color_code),
            $this->getJapanBusinessCatalogItemId($vpn, $color_code),
        ];
    }

    /**
     * @param \Magento\Catalog\Model\Product|null $product
     * @param $productData
     * @param $imageExists
     * @param $dropboxSyncStatus
     */
    public function saveProductData($productData, $vpn, $color_code)
    {
        $productStyle = $productData->productStyleV1;
        $resourceUrl = substr($productData->resourceUrl, 0, strpos($productData->resourceUrl, 'resources'));
        $isResourceJapan = (strpos($resourceUrl, 'gap.co.jp') !== false);
        $isResourceFactory = (strpos($resourceUrl, 'gapfactory.com') !== false);

        $this->downloadProductImages($productStyle, $vpn, $color_code, $resourceUrl,$vpn,$color_code);
    }



    /**
     * Download images
     * only get main image after matching from styleColorImaageMap
     * For rest all images find which productStyleVariantList.productStyleColors.productStyleColorImages.styleColorImagesMap has more image shot captured
     * and download them
     * @param \Magento\Catalog\Model\Product|null $product
     * @param $productStyle
     * @param $resourceUrl
     * @param $dropboxSyncStatus
     */
    public function downloadProductImages($productStyle, $vpn, $color_code, $resourceUrl,$factory_vpn,$factory_color_code)
    {
        $businessCatalogItemIds = $this->getAllProductBusinessIds($vpn, $color_code,$factory_vpn, $factory_color_code);
        //if we use color code as part of vpn we get $productStyle->productStyleVariantList without array.
        $variants = $this->convertStdObjectToArray($productStyle->productStyleVariantList);
        $imageCount = 0;
        $mainImageFound = false;
        foreach ($variants as $variant) {
            $productStyleColors = $this->convertStdObjectToArray($variant->productStyleColors);
            foreach ($productStyleColors as $productStyleColor) {
                if (in_array($productStyleColor->businessCatalogItemId, $businessCatalogItemIds, true)) {
                    if ($mainImageFound) {
                        break;//Main Image is already downloaded from variant.
                    }
                    //Found the matching style color. Download main image from matching color
                    if (!property_exists($productStyleColor->productStyleColorImages, 'styleColorImagesMap')) {
                        $this->updateVPNData("", "", "failed", $vpn, $color_code);
                        return;
                    }
                    $styleColorImagesMap = (array )$productStyleColor->productStyleColorImages->styleColorImagesMap;
                    $mainImageMappingExt = $this->altayerImageMapping[$this->MAIN_IMAGE_SUFFIX];
                    foreach ($mainImageMappingExt as $gapSWExt) {
                        if (array_key_exists($gapSWExt, $styleColorImagesMap)) {
                            break;
                        }
                    }
                    $swatchImageMappingExt = $this->altayerImageMapping[$this->SWATCH_IMAGE_SUFFIX];
                    foreach ($swatchImageMappingExt as $gapSWExt) {
                        if (array_key_exists($gapSWExt, $styleColorImagesMap)) {
                            $gapSWUrl = $styleColorImagesMap[$gapSWExt];
                            break;
                        }
                    }
                }
                if (property_exists($productStyleColor, 'productStyleColorImages')) {
                    $count = count((array )$productStyleColor->productStyleColorImages->styleColorImagesMap);
                    if ($count > $imageCount) {
                        $imageCount = $count;
                        $gapCatalogItemImageMap = $productStyleColor->productStyleColorImages->styleColorImagesMap;
                    }
                }

            }
            //Main image is already downloaded from a variant. Ignore other variants
            if ($mainImageFound)
                break;
        }

        //Now download other extensions
        $otherColorImagesMap = (array)$gapCatalogItemImageMap;
        if (isset($otherColorImagesMap["AV9_VD"])) {
            $posterURL = " ";
            $videoURL = $resourceUrl . $otherColorImagesMap["AV9_VD"];
            if (isset($otherColorImagesMap["AV9_Z"])) {
                $posterURL = $resourceUrl . $otherColorImagesMap["AV9_Z"];
            } elseif (isset($otherColorImagesMap["PRST_IMG"])) {
                $posterURL = $resourceUrl . $otherColorImagesMap["PRST_IMG"];
            }
            $this->_logger->info("Gap Video URL  - " . $videoURL . "\n" . " Image URL : " . $posterURL);
            $this->updateVPNData($videoURL, $posterURL, "success", $vpn, $color_code);
            
        }

    }


    /**
     * @param \Magento\Catalog\Model\Product|null $product
     * @param $otherColorImagesMap
     * @param $resourceUrl
     * @param $parent_id
     * @param $dropboxSyncStatus
     */
    public function downloadProductVideos($otherColorImagesMap, $resourceUrl, $vpn, $color_code)
    {
        foreach ($this->altayerVideoMapping as $altayer_video_suffix => $gapVideoExtensions) {
            foreach ($gapVideoExtensions as $gapExtn) {
                if (array_key_exists($gapExtn, $otherColorImagesMap)) {
                    $gapVideoUrl = $otherColorImagesMap[$gapExtn];
                    $video_url = $parent_id . '_' . $vpn . '_' . $color_code . '_' . $altayer_video_suffix . '.MP4';
                    $result = $this->downloadImage($resourceUrl, $gapVideoUrl, $video_url, $dropboxSyncStatus, 100, false);
                }
            }
        }
    }

    /**
     * @param $productStyle
     * @param $product
     * @return bool
     */
    protected function isValidStyleColor($productStyle, $vpn, $color_code)
    {
        $valid = false;

        $businessCatalogItemIds = $this->getAllProductBusinessIds($vpn, $color_code);
        $variants = $this->convertStdObjectToArray($productStyle->productStyleVariantList);
        foreach ($variants as $variant) {
            if (!property_exists($variant, 'productStyleColors')) {
                continue;
            }
            $productStyleColors = $this->convertStdObjectToArray($variant->productStyleColors);
            foreach ($productStyleColors as $productStyleColor) {
                if (in_array($productStyleColor->businessCatalogItemId, $businessCatalogItemIds, true)) {
                    $valid = true;
                    break;
                }
            }
        }
        return $valid;
    }

    /**
     * @param $text
     * @return mixed|string
     */
    protected function slugify($text)
    {
        // replace non letter or digits by -
        $text = preg_replace('~[^\pL\d]+~u', '-', $text);

        // transliterate
        $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);

        // remove unwanted characters
        $text = preg_replace('~[^-\w]+~', '', $text);

        // trim
        $text = trim($text, '-');

        // remove duplicate -
        $text = preg_replace('~-+~', '-', $text);

        // lowercase
        $text = strtolower($text);

        if (empty($text)) {
            return 'n-a';
        }

        return $text;
    }


    /**
     * Saves Item description .
     * @param $product
     * @param $productStyle
     */
    public function saveProductDescription($product, $productStyle)
    {
        $detailedDescription = '';
        $fitAndSizing = '';
        $fabricDetails = '';
        $infoTabs = $this->convertStdObjectToArray($productStyle->infoTabs);
        foreach ($infoTabs as $infoTab) {
            if (!property_exists($infoTab, 'productBulletAttributes')) {
                continue;
            }
            if ($infoTab->productAttributeGroupName === 'Overview') {
                if (property_exists($infoTab, 'productCopyAttributes')) {
                    $copyAttributes = $this->convertStdObjectToArray($infoTab->productCopyAttributes);
                    foreach ($copyAttributes as $copyAttribute) {
                        $detailedDescription .= '<p>' . $copyAttribute->displayText . '</p>';
                        $this->_logger->info("Description Updated for sku : " . $product->getSku());
                    }
                }
                $overviewAttributes = $this->convertStdObjectToArray($infoTab->productBulletAttributes);
                foreach ($overviewAttributes as $productBulletAttribute) {
                    $detailedDescription .= '<p>' . $productBulletAttribute->displayText . '</p>';
                }
                $detailedDescription .= '<p># ' . $product->getData('vendor_product_number') . '</p>';
                $product->addAttributeUpdate("description", $detailedDescription, 0);
            }
            if (strpos($infoTab->productAttributeGroupName, "Fit") !== false) {
                $fitAndSizingAttributes = $this->convertStdObjectToArray($infoTab->productBulletAttributes);
                foreach ($fitAndSizingAttributes as $productBulletAttribute) {
                    $fitAndSizing .= '<p>' . $productBulletAttribute->displayText . '</p>';
                }
                $product->addAttributeUpdate("fit_and_sizing", $fitAndSizing, 0);
            }
            if ($infoTab->productAttributeGroupName === 'Fabric & Care') {
                $fabricAttributes = $this->convertStdObjectToArray($infoTab->productBulletAttributes);
                foreach ($fabricAttributes as $productBulletAttribute) {
                    $fabricDetails .= '<p>' . $productBulletAttribute->displayText . '</p>';
                }
            }
        }
        if (property_exists($productStyle, 'fabricContentAttributes') && !empty($productStyle->fabricContentAttributes) && empty($fabricDetails)) {
            $fabricContents = $this->convertStdObjectToArray($productStyle->fabricContentAttributes);
            foreach ($fabricContents as $fabricContent) {
                $fabricDetails .= '<p>' . $fabricContent->fabricContentPercentage . '% ' . $fabricContent->fabricContentName . '</p>';
            }
        }
        if (property_exists($productStyle, 'careInstructionText') && !empty($productStyle->careInstructionText)) {
            $fabricDetails .= '<p>' . $productStyle->careInstructionText . '</p>';
        }
        $isImported = $productStyle->isImported;
        if ($isImported) {
            $fabricDetails .= '<p> Imported </p>';
        }
        $product->addAttributeUpdate("fabric_and_care", $fabricDetails, 0);
    }


    public function saveSizeGroup($product, $productStyle)
    {
        $vpn = $product->getData('vendor_product_number');
        $color_code = $product->getData('brand_color');
        $factory_vpn = $product->getData('factory_vpn');
        $factory_color_code = $product->getData('factory_color_code');

        $businessCatalogItemIds = $this->getAllProductBusinessIds($vpn, $color_code, $factory_vpn, $factory_color_code);
        //if we use color code as part of vpn we get $productStyle->productStyleVariantList without array.
        $variants = $this->convertStdObjectToArray($productStyle->productStyleVariantList);
        foreach ($variants as $variant) {
            $productStyleColors = property_exists($variant, 'productStyleColors') ? $this->convertStdObjectToArray($variant->productStyleColors) : [];
            foreach ($productStyleColors as $productStyleColor) {
                if (in_array($productStyleColor->businessCatalogItemId, $businessCatalogItemIds, true)) {
                    $gap_color = $productStyleColor->colorName;
                    $size_group = $variant->variantName;
                    break;
                }
            }
            if (!empty($size_group)) {
                $product->addAttributeUpdate("size_group", $size_group, 0);
                break;
            }
        }

        if (!empty($gap_color)) {
            $product->addAttributeUpdate("gap_color", $gap_color, 0);
        }
    }

    /**
     * Method to solve classic json problem with arrays
     * @param $stdClass
     * @return array
     */
    public function convertStdObjectToArray($stdClass)
    {
        if (!is_array($stdClass)) {
            $arrayObj = [$stdClass];
        } else {
            $arrayObj = $stdClass;
        }
        return $arrayObj;
    }

    /**
     * @return mixed|null
     */
    public function fetchProductsAvailability($currentPage = 1, $pageSize = NULL)
    {
        $aieProduct = $this->aieProductFactory->create();

        $apiUrl = $this->_scopeConfig->getValue(self::GAP_CATALOG_API_URL);
        $apiKey = $this->_scopeConfig->getValue(self::GAP_CATALOG_API_KEY);
        $apiPageSize = $pageSize ?? $this->_scopeConfig->getValue(self::GAP_CATALOG_API_PAGE_SIZE);

        // TODO: Support Multiple {brand}/{market}
        $apiBrandMarket = $this->_scopeConfig->getValue(self::GAP_CATALOG_API_BRAND_AND_MARKET);

        $productFactory = $this->_productFactory->create();
        for ($apiPage = $totalPages = $currentPage; $apiPage <= $totalPages; $apiPage++) {
            $catalogData = $this->fetchGapProductsCatalog($apiUrl, $apiPage, $apiPageSize, $apiBrandMarket, $apiKey);
            if (empty($catalogData) or !isset($catalogData->_embedded))
                continue;
            $totalPages = $catalogData->page->totalPages;
            foreach ($catalogData->_embedded->styles as $style) {
                $vpn = preg_replace('/^000/', '', $style->businessId);
                if (!isset($style->styleColors))
                    continue;
                foreach ($style->styleColors as $styleColor) {
                    $colorCode = substr($styleColor->businessId, -3, 2);
                    $availabilityDate = null;
                    if (isset($styleColor->startDate) and $styleColor->startDate != null) {
                        $availabilityDate = date('Y-m-d', strtotime($styleColor->startDate));
                    }

                    $status = $styleColor->approvalStatus;

                    $products = $aieProduct->getCollection()
                        ->addFieldToFilter('vpn', ['eq' => $vpn])
                        ->addFieldToFilter('color_code', $colorCode)
                        ->addFieldToFilter('api_status', [['neq' => $status], ['null' => true]])
                        ->addFieldToFilter('online_date', [['neq' => $availabilityDate], ['null' => true]]);

                    if (!$products)
                        continue;
                    /** @var \Altayer\GapIntegration\Model\Product $product */
                    foreach ($products as $product) {
                        $product->setOnlineDate($availabilityDate)->setApiStatus($status);
                        if ($status == 'BUILD_READY' && $product->getStatus() != self::PRODUCT_STATUS_OFFLINE) {
                            $product->setStatus(self::PRODUCT_STATUS_OFFLINE);
                        }
                        try {
                            $product->save();
                        } catch (\Exception $e) {
                            $this->_logger->addError('Error saving product: ' . $e->getMessage());
                        }

                        $this->_logger->info("Product",
                            [
                                'sku' => $product->getData('sku'),
                                'vpn' => $vpn,
                                'color_code' => $colorCode,
                                'availability' => $availabilityDate,
                                'status' => $status,
                            ]
                        );
                    }
                }
            }
        }
    }

    protected function getCatalogApiUrl($url, $page, $pageSize, $brandMarket)
    {
        return "$url$brandMarket?includeSkus=false&page=$page&size=$pageSize&sort=itemId,asc";
    }


    /**
     * @return bool
     */
    private function isConfigurationPresent()
    {
        $sourceArray = $this->_mappings->toOptionArray();
        if (empty($sourceArray)) {
            return false;
        }
        return true;
    }

    /**
     * @return bool|string
     */
    protected function getDate()
    {
        $timezone = $this->scopeConfig->getValue('general/locale/timezone');
        @date_default_timezone_set($timezone);
        return date("Y-m-d H:i:s");
    }

    /**
     * @return array
     */
    private function getAllConfiguredCurrency()
    {
        $currencyArray = [];
        $data = $this->_mappings->getRawMapping();
        foreach ($data as $country => $currency) {
            $currencyArray [] = $currency;
        }
        return $currencyArray;
    }

    /**
     * @return array
     */
    private function getCurrencyToCountryMap()
    {
        $data = $this->_mappings->getRawMapping();
        $currencyToCountryMap = array_flip($data);
        return $currencyToCountryMap;
    }

    /**
     * @param $currency
     * @param $ignoreEmailIds
     * @return array
     */
    private function getLastPlacedOrder($currency, $ignoreEmailIds)
    {
        $value = "'" . implode('\',\'', $currency) . "'";

        if (!$ignoreEmailIds) {
            $ignoreEmailIds = '\' \'';
        }

        $sql = "SELECT a.increment_id        AS `order_number`, 
                       a.created_at          AS `order_date`, 
                       a.store_currency_code AS `order_currency`, 
                       a.customer_email      AS `customer_email`, 
                       p.method              AS `payment_method` 
                FROM   sales_order a 
                       INNER JOIN (SELECT increment_id, 
                                          Max(entity_id) AS maxid 
                                   FROM   sales_order 
                                   WHERE  store_currency_code IN ($value) 
                                   AND customer_email NOT IN ($ignoreEmailIds)
                                   GROUP  BY store_currency_code) AS b 
                               ON a.entity_id = b.maxid 
                       INNER JOIN sales_order_payment p 
                               ON a.entity_id = p.parent_id; ";

        $data = $this->connection->fetchAll($sql);

        return $data;
    }


    /**
     * @param $currency
     * @param $ignoreEmailIds
     * @return array
     */
    private function isOrderPlaced($currency, $ignoreEmailIds)
    {

        if (!$ignoreEmailIds) {
            $ignoreEmailIds = '\' \'';
        }

        $interval = $this->scopeConfig->getValue(self::XML_PATH_ORDER_INTERVAL);
        $interval = (!empty($interval)) ? $interval : 1;

        $value = "'" . implode('\',\'', $currency) . "'";
        $ord = [];
        $sql = "SELECT DISTINCT store_currency_code AS `curerncy`
                FROM   sales_order
                WHERE  store_currency_code IN ($value)
                       AND created_at >= Date_sub(Now(), INTERVAL $interval hour)
                       AND customer_email NOT IN ($ignoreEmailIds)
                ORDER  BY created_at DESC;";

        $data = $this->connection->fetchAll($sql);

        foreach ($data as $orderCurrency) {
            $ord[] = $orderCurrency['curerncy'];
        }

        $missedOrderCountry = array_diff($this->getAllConfiguredCurrency(), $ord);

        return $missedOrderCountry;
    }

    /**
     * @return mixed
     * @throws \Exception
     */
    private function getVPNData()
    {
        try {
            //$select = "SELECT vpn,color_code FROM atg_gap_video                        WHERE (status <> 'success' OR status IS NULL) ;";
            $select = "SELECT vpn,color_code FROM atg_gap_video   WHERE  status is null;";

            $vpnData = $this->connection->fetchAll($select);
        } catch (\Exception $e) {
            $this->_logger->info("Error get vpn information " . $e->getMessage());
            throw new \Exception($e);
        }
        return $vpnData;
    }

    /**
     * @param $videoURL
     * @param $previewURL
     * @param $status
     * @param $vpn
     * @param $color
     * @return array
     * @throws \Exception
     */
    public function updateVPNData($videoURL, $previewURL, $status, $vpn, $color)
    {
        try {
            $sql = "UPDATE atg_gap_video t SET t . video_url = '$videoURL', t . preview_url = '$previewURL', t . status = '$status' WHERE t . vpn = '$vpn'
            and t . color_code = '$color' ";
            $this->_logger->info("Update query : " . $sql);
            $data = $this->connection->query($sql);
            // Update in gap enrichment grid
            $enrichmentSql =  "UPDATE altayer_item_enrichment t SET t . gap_video_preview_url = '$previewURL' WHERE t . vpn = '$vpn'
            and t . color_code = '$color' ";
            $enrichmentData = $this->connection->query($enrichmentSql);
        } catch (\Exception $e) {
            $this->_logger->info("Error updating vpn information " . $vpn . $e->getMessage());
            throw new \Exception($e);
        }
        return $data;
    }

    /**
     *
     */
    public function createAndPopulateData()
    {
        $createSql = "CREATE TABLE atg_gap_video (
                    vpn VARCHAR(30) NOT NULL,
                    color_code VARCHAR(30) NOT NULL,
                    video_url VARCHAR(100),
                    preview_url VARCHAR(100),
                    status varchar(10)
                     );";

        $populateSql = "INSERT INTO atg_gap_video (vpn,color_code) 
                        SELECT DISTINCT  vpn,color_code 
                        FROM altayer_item_enrichment;";

    }

}
