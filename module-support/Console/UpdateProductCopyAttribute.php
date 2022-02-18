<?php
/**
 * UpdateProductCopyAttribute.php
 * @package   Altayer\Support\Console
 * @author    Amrendra <amrendragr8@gmail.com>
 */

namespace Altayer\Support\Console;

use Altayer\Integration\Model\ResourceModel\RMSItem\CollectionFactory;
use Altayer\RMSIntegration\Model\ResourceModel\UDA\CollectionFactory as UDACollectionFactory;
use Altayer\Support\Helper\Data as HelperData;
use Altayer\Integration\Helper\RepositoryHelper;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Psr\Log\LoggerInterface as PsrLogger;
use Altayer\Support\Model\ResourceModel\AtgVpnData;
use Altayer\Integration\Helper\DropBoxHelper;
use Magento\Framework\App\ResourceConnection;
use Altayer\Integration\Helper\Utility as ImportUtility;



/**
 * Class UpdateSkuVpnData
 * @package Altayer\Support\Console
 */
class UpdateProductCopyAttribute extends Command
{
    const NAME = 'catalog:products:UpdateProductCopyAttribute';
    const MAIN_IMAGE_SUFFIX_URL = 'altayer_integration/general/main_image_suffix';
    const SWATCH_IMAGE_SUFFIX_URL = 'altayer_integration/general/swatch_image_suffix';
    const LOCK_ID = 'update_product_attributes';
    const IMAGE_UPLOAD_DROPBOX_ENABLED = 'altayer_integration/dropbox/enable_image_proccess_dropbox';

    protected $_dropboxHelper;
    protected $MAIN_IMAGE_SUFFIX;
    protected $SWATCH_IMAGE_SUFFIX;
    /**
     * @var CollectionFactory
     */
    protected $_itemCollectionFactory;
    /**
     * @var ResourceConnection
     */
    protected $resource;
    /**
     * @var UDACollectionFactory
     */
    protected $_udaCollectionFactory;
    /**
     * @var LockInterface
     */
    protected $_lockFile;
    protected $_utility;


    /**
     * UpdateProductCopyAttribute constructor.
     * @param PsrLogger $logger
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param HelperData $helperData
     * @param \Altayer\GapIntegration\Model\ResourceModel\Product\CollectionFactory $enrichProduct
     * @param \Magento\Catalog\Model\ProductFactory $productFactory
     * @param \Altayer\Support\Model\AtgVpnDataFactory $atgVpnDataFactory
     * @param \Altayer\Support\Model\AtgVpnColorMappingFactory $atgVpnColorMappingFactory
     * @param DropBoxHelper $dropboxHelper
     * @param CollectionFactory $itemCollectionFactory
     * @param ResourceConnection $resource
     * @param UDACollectionFactory $udaCollectionFactory
     * @param ImportUtility $utility
     * @param \Altayer\OrderExport\Api\LockInterfaceFactory $lockFileFactory
     * @param null $name
     */
    public function __construct(
        PsrLogger $logger,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        HelperData $helperData,
        \Altayer\GapIntegration\Model\ResourceModel\Product\CollectionFactory $enrichProduct ,
        \Magento\Catalog\Model\ProductFactory $productFactory,
        \Altayer\Support\Model\AtgVpnDataFactory $atgVpnDataFactory,
        \Altayer\Support\Model\AtgVpnColorMappingFactory $atgVpnColorMappingFactory,
        DropBoxHelper $dropboxHelper,
        CollectionFactory $itemCollectionFactory,
        ResourceConnection $resource,
        UDACollectionFactory $udaCollectionFactory,
        ImportUtility $utility,
        \Altayer\OrderExport\Api\LockInterfaceFactory $lockFileFactory,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        $name = null
    )
    {
        parent::__construct($name);
        $this->_logger = $logger;
        $this->scopeConfig = $scopeConfig;
        $this->helperData = $helperData;
        $this->enrichmentProducts = $enrichProduct;
        $this->_productFactory = $productFactory;
        $this->_atgVpnData = $atgVpnDataFactory;
        $this->_atgVpnColorMapping = $atgVpnColorMappingFactory;
        $this->_dropboxHelper = $dropboxHelper;
        $this->_itemCollectionFactory = $itemCollectionFactory;
        $this->resource = $resource;
        $this->_udaCollectionFactory = $udaCollectionFactory;
        $this->_lockFile = $lockFileFactory;
        $this->_utility = $utility;
        $this->_storeManager = $storeManager;
        $this->MAIN_IMAGE_SUFFIX = $scopeConfig->getValue(self::MAIN_IMAGE_SUFFIX_URL);
        $this->SWATCH_IMAGE_SUFFIX = $scopeConfig->getValue(self::SWATCH_IMAGE_SUFFIX_URL);
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this->setName(self::NAME);
        $this->setDescription('Update Sku VPN data from Vpn table');
        parent::configure();
    }

    /**
     * export report orders
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $enrichProducts = $this->enrichProducts();
    }

    /**
     * Method to enrich products on magento
     *
     */
    public function enrichProducts()
    {
        $lockFile = $this->_utility->getLockFile(self::LOCK_ID,'UpdateProductCopyAttribute');
        if (!$lockFile) {
            return;
        }
        try {
            $lockFile->lock();
            $startTime = microtime(true);
            $enrichmentProducts = $this->enrichmentProducts->create();
            $enrichmentProducts->addFieldToFilter('status', ['in' => ['FAILED', 'NEW']]);
            $this->_logger->info("enrichProducts : About to enrich product count  " . $enrichmentProducts->count());

            foreach ($enrichmentProducts as $p) {
                $this->_storeManager->setCurrentStore('admin');
                $product = $this->_productFactory->create()->loadByAttribute('sku', $p->getSku());
                if (empty($product)) {
                    $this->_logger->info('Product not found, hence skipping : ', [$p->getSku()]);
                    continue;
                }else{
                    $connection = $this->resource->getConnection();
                    $this->checkSkuVpn($product,$connection,$p);
                }
            }
        } catch (\Exception $e) {
            $this->_logger->info('Exception while pushing messages to queue : ' . $e->getMessage());
        } finally {
            $endTime = microtime(true);
            $this->_logger->info('enrichProducts :Messages are pushed on enrichment queue in :' . ($endTime - $startTime) . ' Seconds ' . "\n");
            $lockFile->unlock();

        }
    }

    public function checkSkuVpn($product,$connection,$p)
    {
        $vpn = $product->getData('vendor_product_number');
        $color_code = $product->getData('brand_color');

        $sql = "SELECT avd.vpn,avd.name,avd.description,avd.size_and_fit,avd.fabric_contents,avd.care_instructions,avd.is_factory,mapping.color_code,
        mapping.in_image,mapping.bk_image,mapping.fr_image,mapping.cu_image,mapping.pk_image,mapping.sw_image,mapping.poster_url,mapping.video_url 
        FROM atg_vpn_color_mapping mapping
        LEFT JOIN atg_vpn_data avd on avd.vpn = mapping.vpn
        WHERE mapping.vpn = '".$vpn."' and mapping.color_code = '".$color_code."' and avd.status = 'Approved' and mapping.status = 'Approved'" ;
        $result = $connection->fetchRow($sql);
        if(empty($result)){
            $vpn = $product->getData('factory_vpn');
            $color_code = $product->getData('factory_color_code');
            if(!empty($vpn) && !empty($color_code)){
                $sql = "SELECT avd.vpn,avd.name,avd.description,avd.size_and_fit,avd.fabric_contents,avd.care_instructions,avd.is_factory,mapping.color_code,
                mapping.in_image,mapping.bk_image,mapping.fr_image,mapping.cu_image,mapping.pk_image,mapping.sw_image,mapping.poster_url,mapping.video_url 
                FROM atg_vpn_color_mapping mapping
                LEFT JOIN atg_vpn_data avd on avd.vpn = mapping.vpn
                WHERE mapping.vpn = '".$vpn."' and mapping.color_code = '".$color_code."' and avd.status = 'Approved' and mapping.status = 'Approved'" ;
                $result = $connection->fetchRow($sql);
            }
        }

        if(!empty($result)){
            $this->_logger->info('Product Attribute updating for sku : ' . $product->getData('sku'));
            $this->updateProductVpnData($product,$result,$p);
        }else{
            if(empty($vpn))
            {
                $vpn = $product->getData('vendor_product_number');
                $color_code = $product->getData('brand_color');
            }
            $this->updateEnrichment($vpn,$color_code,0,$p,0,'');
            $this->_logger->info('VPN and Color code not matched for sku : ' . $product->getData('sku'));
        }
    }

    public function updateProductVpnData($product,$vpnRecord,$p)
    {
        try {
            $parent_id = $this->getUDADetailsBySKUId($product->getData('sku'), "parent");
            $fabric_contents = $vpnRecord['fabric_contents'] . $vpnRecord['care_instructions'];
            $description = $vpnRecord['description'] . '<p>#' . $product->getData('vendor_product_number') . '</p>';

            $product->setData('name', $vpnRecord['name']);
            $product->setData('fit_and_sizing', $vpnRecord['size_and_fit']);
            $product->setData('fabric_and_care', $fabric_contents);
            $product->setData('description', $description);
            $product->setData('video_url', $vpnRecord['video_url']);
            $product->setData('gap_poster_preview_url', $vpnRecord['poster_url']);
            $product->save();

            $vpn = $vpnRecord['vpn'];
            $color_code = $vpnRecord['color_code'];
            $updateByFactory = $vpnRecord['is_factory'];
            $this->updateEnrichment($vpn, $color_code, $updateByFactory, $p, 1, $vpnRecord['video_url']);
            $imageUploadEnabled = $this->scopeConfig->getValue(self::IMAGE_UPLOAD_DROPBOX_ENABLED);
            if($imageUploadEnabled) {
                $dropboxSyncStatus = $this->getDropboxSyncStatus($product->getData('sku'));
                if (!empty($vpnRecord['in_image'])) {
                    $imageName = $parent_id . '_' . $product->getData('vendor_product_number') . '_' . $product->getData('brand_color') . '_' . $this->MAIN_IMAGE_SUFFIX . '.JPG';
                    $this->uploadToDropbox($vpnRecord['in_image'], $imageName, $dropboxSyncStatus);
                }
                if (!empty($vpnRecord['sw_image'])) {
                    $imageName = $parent_id . '_' . $product->getData('vendor_product_number') . '_' . $product->getData('brand_color') . $this->SWATCH_IMAGE_SUFFIX;
                    $this->uploadToDropbox($vpnRecord['sw_image'], $imageName, $dropboxSyncStatus);
                }
                if (!empty($vpnRecord['bk_image'])) {
                    $imageName = $parent_id . '_' . $product->getData('vendor_product_number') . '_' . $product->getData('brand_color') . '_bk' . '.JPG';
                    $this->uploadToDropbox($vpnRecord['bk_image'], $imageName, $dropboxSyncStatus);
                }
                if (!empty($vpnRecord['fr_image'])) {
                    $imageName = $parent_id . '_' . $product->getData('vendor_product_number') . '_' . $product->getData('brand_color') . '_fr' . '.JPG';
                    $this->uploadToDropbox($vpnRecord['fr_image'], $imageName, $dropboxSyncStatus);
                }
                if (!empty($vpnRecord['cu_image'])) {
                    $imageName = $parent_id . '_' . $product->getData('vendor_product_number') . '_' . $product->getData('brand_color') . '_cu' . '.JPG';
                    $this->uploadToDropbox($vpnRecord['cu_image'], $imageName, $dropboxSyncStatus);
                }
                if (!empty($vpnRecord['pk_image'])) {
                    $imageName = $parent_id . '_' . $product->getData('vendor_product_number') . '_' . $product->getData('brand_color') . '_pk' . '.JPG';
                    $this->uploadToDropbox($vpnRecord['pk_image'], $imageName, $dropboxSyncStatus);
                }
            }
        }catch (\Exception $e){
            $this->updateEnrichment($vpn, $color_code, $updateByFactory, $p, 0, '');
        }
    }

    /**
     * @param null $imageUrl
     * @param $imageName
     * @return bool
     */
    public function uploadToDropbox($imageUrl = null, $imageName,$dropboxSyncStatus)
    {
        if ($this->_dropboxHelper->enable()) {
            if ($imageUrl == null || empty($imageName)) {
                $this->_logger->addError("Invalid input");
                return false;
            }
            $dropbox = $this->_dropboxHelper->getDropboxConnection();
            $dropboxLocation = $this->_dropboxHelper->getDropboxLocation() . '/Api_Images/';
//            if ($dropboxSyncStatus) {
//                //status is true, image is already synched , we need to resync by deleting the existing image
//                $this->_dropboxHelper->deleteFileByURL($dropbox, $dropboxLocation . $imageName);
//                $this->_logger->info("Delete Image from Dropbox - $imageUrl with parameters " . $imageName);
//            }
            if ($this->_dropboxHelper->uploadFileByURL($dropbox, $dropboxLocation . $imageName, $imageUrl)) {
                $this->_logger->info("Successfully Uploaded Image to Dropbox - $imageUrl with parameters " . $imageName);
                return true;
            } else {
                $this->_logger->info("Error while Uploading Image to Dropbox - $imageUrl with parameters " . $imageName);
                return false;
            }
        }
    }

    /**
     * @param $sku
     * @return bool
     */
    public function getDropboxSyncStatus($sku)
    {
        /** @var  $itemCollection  \Altayer\Integration\Model\ResourceModel\RMSItem\Collection */
        $itemCollection = $this->_itemCollectionFactory->create()
            ->addFieldToSelect('*')
            ->addFieldToFilter('skuId', $sku)
            ->addFieldToFilter('dropbox_sync', 1);
        if ($itemCollection->getSize() == 0) {
            return false;
        }
        return true;
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

    public function updateEnrichment($vpn,$color_code,$updateByFactory,$p,$status,$video_url){
        if($status){
            $apiSource = 'Gap';
            if ($updateByFactory) {
                $apiSource = 'Gap Factory';
            }
            $remark = 'SUCCESS for vpn :' . $vpn . ' Color Code: ' . $color_code . ' from ' . $apiSource;
            $p->setStatus('SUCCESS');
            $p->setRemark($remark);
            $p->setVideoUrl($video_url);
            $p->setApiSource($apiSource);
        }else {
            $remark = 'No data found for VPN :' . $vpn . ' and Color Code: ' . $color_code;
            $p->setRemark($remark);
            $p->setStatus('FAILED');
        }
        $p->save();
    }

}