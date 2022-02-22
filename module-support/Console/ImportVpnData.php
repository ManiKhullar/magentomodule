<?php
/**
 * ImportVpnData.php
 * @package   Altayer\Support\Console
 * @author   Mani <kmanidev6@gmail.com>
 */

namespace Altayer\Support\Console;

use Altayer\Integration\Helper\AWSHelper;
use Altayer\Integration\Helper\Data;
use Altayer\Support\Helper\Data as HelperData;
use Altayer\Integration\Helper\RepositoryHelper;
use Altayer\Integration\Model\FileArchiver;
use Altayer\RMSIntegration\Model\ResourceModel\ItemDetails;
use Altayer\Sales\Model\Utility;
use Altayer\Support\Model\Helper as Helper;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;
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
use phpseclib\Net\SFTP;
use Altayer\Support\Model\ResourceModel\AtgVpnData;



/**
 * Class ImportVpnData
 * @package Altayer\Support\Console
 */
class ImportVpnData extends Command
{
    const NAME = 'catalog:products:ImportVpnData';

    const VPN_IMPORT_ENABLED = 'altayer_integration/vpnimportdata/vpn_import_data';

    const XML_PATH_SFTP_HOST = 'altayer_reminder/reminder_ftp/sftp_host';
    const XML_PATH_SFTP_USER = 'altayer_reminder/reminder_ftp/sftp_user';
    const XML_PATH_SFTP_PASS = 'altayer_reminder/reminder_ftp/sftp_pass';
    const XML_PATH_SFTP_DIR = 'altayer_reminder/reminder_ftp/sftp_directory';

    const GAP_FEED_FILE_DIR = 'FEED/';
    const GAP_FILE_TYPE = 'Gap Feed';
    const ATG_VPN_DATA_TABLE = 'atg_vpn_data';
    const ATG_VPN_COLOR_MAPPING_TABLE = 'atg_vpn_color_mapping';

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
     * @var $directory
     */
    protected $directory;

    /**
     * @var $directoryList
     */
    protected $directoryList;

    /**
     * @var $_awsHelper
     */
    protected $_awsHelper;

    /**
     * @var $bucketName
     */
    protected $bucketName;

    /**
     * @var $prefix
     */
    protected $prefix;

    /**
     * @var $downloadLocation
     */
    protected $downloadLocation;

    /**
     * @var $client
     */
    protected $client;

    /**
     * @var FileArchiver
     */
    private $archiver;
    protected $filesToArchive = array();



    /**
     * ImportVpnData constructor.
     * @param Utility $utility
     * @param Csv $csv
     * @param ResourceConnection $resource
     * @param PsrLogger $logger
     * @param Helper $helper
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param ObjectManagerInterface $objectManager
     * @param ProductCollectionFactory $productCollectionFactory
     * @param \Magento\Catalog\Api\ProductRepositoryInterface $productloader
     * @param \Altayer\Support\Model\AtgVpnDataFactory $atgVpnDataFactory
     * @param \Altayer\Support\Model\AtgVpnColorMappingFactory $atgVpnColorMappingFactory
     * @param Filesystem $filesystem
     * @param DirectoryList $directoryList
     * @param AWSHelper $awsHelper
     * @param Data $data
     * @param RepositoryHelper $repositoryHelper
     * @param FileArchiver $archiver
     * @param AtgVpnData $atgVpnModel
     * @param ItemDetails $itemDetailsResourceModel
     * @param VpnData $vpnData
     * @param null $name
     * @throws FileSystemException
     */
    public function __construct(
        Utility $utility,
        Csv $csv,
        ResourceConnection $resource,
        PsrLogger $logger,
        Helper $helper,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        ObjectManagerInterface $objectManager,
        ProductCollectionFactory $productCollectionFactory,
        \Magento\Catalog\Api\ProductRepositoryInterface $productloader,
        \Altayer\Support\Model\AtgVpnDataFactory $atgVpnDataFactory,
        \Altayer\Support\Model\AtgVpnColorMappingFactory $atgVpnColorMappingFactory,
        Filesystem $filesystem,
        DirectoryList $directoryList,
        AWSHelper $awsHelper,
        Data $data,
        RepositoryHelper $repositoryHelper,
        FileArchiver $archiver,
        AtgVpnData $atgVpnModel,
        ItemDetails $itemDetailsResourceModel,
        HelperData $helperData,
        $name = null
    )
    {
        parent::__construct($name);
        $this->csv = $csv;
        $this->utility = $utility;
        $this->_logger = $logger;
        $this->helper = $helper;
        $this->scopeConfig = $scopeConfig;
        $this->_objectManager = $objectManager;
        $this->_productCollectionFactory = $productCollectionFactory;
        $this->_productloader = $productloader;
        $this->_atgVpnData = $atgVpnDataFactory;
        $this->_atgVpnColorMapping = $atgVpnColorMappingFactory;
        $this->directory         = $filesystem->getDirectoryWrite(
            DirectoryList::VAR_DIR);
        $this->directoryList     = $directoryList;
        $this->_awsHelper = $awsHelper;
        $this->_data = $data;
        $this->_helper = $repositoryHelper;
        $this->archiver = $archiver;
        $this->atgVpnModel = $atgVpnModel;
        $this->connection = $resource->getConnection();
        $this->itemDetailsResourceModel = $itemDetailsResourceModel;
        $this->helperData = $helperData;
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this->setName(self::NAME);
        $this->setDescription('Import VPN data from file');
        parent::configure();
    }

    /**
     * export report orders
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $importVpnEnabled = $this->scopeConfig->getValue(self::VPN_IMPORT_ENABLED);
        if($importVpnEnabled)
        {
            $this->loadInitialConfig();
            $this->importVpnDataFromJson($output);
        }else
        {
            $output->writeln("Please Enable the Missing Configurations");
        }

    }

    private function loadInitialConfig()
    {
        $this->devMode = $this->_data->isDevModeEnable();
        $this->bucketName = $this->_data->getBucketName();
        $this->prefix = $this->_data->getPrefix();
        $this->downloadLocation = $this->_data->getDownloadFileLocation();
        $this->client = $this->_awsHelper->getAWSClientConnection();

    }

    /**
     * @param $rms_dir
     */
    protected function generateGapFeed($rms_dir)
    {
        $gapfeed_items = glob($rms_dir . "1006_AE_GAP_CATALOG_gap*.json");
        $this->filesToArchive = $gapfeed_items;
        $this->markFileAsInProgress($gapfeed_items, self::GAP_FILE_TYPE);

    }

    /**
     * @param $files
     * @param $fileType
     */
    protected function markFileAsProcessed($files, $fileType)
    {
        foreach ($files as $file) {
            $rmsFile = $this->_helper->getRMSFile($fileType, null, $file)->getFirstItem();
            $this->_helper->updateRMSFile($rmsFile, 'processed');


        }
    }

    /**
     * @param $files
     * @param $fileType
     */
    protected function markFileAsInProgress($files, $fileType)
    {
        foreach ($files as $file) {
            if (strrpos($file, '/') >= 0) {
                $file = substr($file, strrpos($file, "/") + 1);
            }
            $this->_helper->populateRMSFile($file, $fileType, 'in-progress');
        }
    }


    /**
     * @param $file
     */
    private function readAndPopulateFeedData($file)
    {
        if ($file == false) {
            $this->_logger->addInfo('---- No Gap Feed file to process.');
            return;
        }
        $fileName = substr($file, strrpos($file, "/") + 1);
        $rmsFile = $this->_helper->populateRMSFile($fileName, self::GAP_FILE_TYPE);
    }
    /**
     * @param $RMSFileCollection
     * @return array
     */
    protected function getFileName($RMSFileCollection)
    {
        $fileName = [];
        if ($RMSFileCollection != null && count($RMSFileCollection)) {
            foreach ($RMSFileCollection as $item) {
                $fileName[] = $item->getFileName();
            }
        }
        return $fileName;
    }

    /**
     * @param $output
     */
    public function importVpnDataFromJson($output)
    {
        try{
            $prefixName = $this->prefix . self::GAP_FEED_FILE_DIR;
            $startTime = microtime(true);
            $this->_awsHelper->prepareAndDownloadFile($this->client, $this->downloadLocation, $this->bucketName, $this->prefix, self::GAP_FEED_FILE_DIR, $prefixName, self::GAP_FILE_TYPE);
            $this->generateGapFeed($this->downloadLocation);
            $this->archiver->archiveFiles($this->filesToArchive, $this->downloadLocation);
            $endTime = microtime(true);
            $this->_logger->addInfo('--Total Execution Time to parse Gap feed :' . $this->downloadLocation . " --> " . ($endTime - $startTime) . ' Seconds ' . "\n");
            $rmsFileCollection = $this->_helper->getRMSFile(self::GAP_FILE_TYPE, "in-progress");
            $rmsFileName = $this->getFileName($rmsFileCollection);
            if($rmsFileCollection->getSize()==0 || count($rmsFileName)==0)
            {
                $output->writeln("Not Any File To Process !!!");
                return $this;
            }
            $batchCount =1000;
            $rowCount = 1;

            foreach($rmsFileName as $filename) {
                $this->_logger->addInfo('---- file name processed.'.$filename);
                $modelVpnData = $this->atgVpnModel->getConnection();
                if ($filename != "") {
                    $filepath = '/tmp/archive/' . $filename;

                    $is_factory = 0;
                    if (strpos($filepath, 'gapfs') !== false) {
                        $is_factory = 1;
                    }
                    try {
                        $handle = fopen($filepath, "r+");
                        $rowCount = 1;
                        $batchRowCount = 1;
                        $contents = fread($handle, filesize($filepath));
                        fclose($handle);
                        $contentsArr = explode("\n", $contents);
                        $cntRow = count($contentsArr);
                        $batchCount = $cntRow%1000;
                        $totalRow = floor($cntRow/1000);
                        $count = 0;
                        foreach ($contentsArr as $rowContent) {
                            if($batchRowCount <= $totalRow)
                            {
                                $batchCount = 1000;
                            }else{
                                $batchCount = $cntRow%1000;
                            }
                            if($count < $cntRow){
                                if (!empty($rowContent)) {
                                    $vpnDataForImport = json_decode($rowContent);
                                }
                                if (empty($vpnDataForImport)) {
                                    continue;
                                }
                                if ($rowCount === 1) {
                                    $modelVpnData->beginTransaction();
                                }
                                $colorCodesArr = array();
                                if (property_exists($vpnDataForImport, 'customerChoice') && !empty($vpnDataForImport->customerChoice)) {
                                    foreach ($vpnDataForImport->customerChoice as $customerChoice) {
                                        $colorCode = '';
                                        if (property_exists($customerChoice, 'colorCode')) {
                                            $colorCode = $customerChoice->colorCode;
                                            $colorCodesArr[] = $colorCode;
                                        }
                                    }
                                }

                                $colorCodes = implode(',', $colorCodesArr);
                                if (!empty($vpnDataForImport)) {
                                    $overviewCopyBullets = $fabricContents = $careInstructions = $styleDescription = $fitAndSizingCopyBullets = '';
                                    $overviewCopyBulletField = $fabricContentField = $careInstructionField = $fitAndSizingCopyBulletField = $returnFieldData = '';

                                    if (property_exists($vpnDataForImport, 'overviewCopyBullets')) {
                                        $overviewCopyBullets = $this->convertStdObjectToArray($vpnDataForImport->overviewCopyBullets);
                                        $overviewCopyBulletField = $this->convertHtml($overviewCopyBullets,$returnFieldData);
                                    }
                                    if (property_exists($vpnDataForImport, 'fabricContents')) {
                                        $fabricContents = $this->convertStdObjectToArray($vpnDataForImport->fabricContents);
                                        $fabricContentField = $this->convertHtml($fabricContents,$returnFieldData);
//
                                    }
                                    if (property_exists($vpnDataForImport, 'careInstructions')) {
                                        $careInstructions = $this->convertStdObjectToArray($vpnDataForImport->careInstructions);
                                        $careInstructionField = $this->convertHtml($careInstructions,$returnFieldData);
//
                                    }
                                    if (property_exists($vpnDataForImport, 'styleDescription')) {
                                        $styleDescription = $vpnDataForImport->styleDescription;
                                    }
                                    if (property_exists($vpnDataForImport, 'fitAndSizingCopyBullets')) {
                                        $fitAndSizingCopyBullets = $this->convertStdObjectToArray($vpnDataForImport->fitAndSizingCopyBullets);
                                        $fitAndSizingCopyBulletField = $this->convertHtml($fitAndSizingCopyBullets,$returnFieldData);
//
                                    }

                                    $modelVpnData->beginTransaction();

                                    $model = $this->_atgVpnData->create();
                                    $model->setData("vpn", $vpnDataForImport->styleNumber);
                                    $model->setData("name", $styleDescription);
                                    $model->setData("description", $overviewCopyBulletField);
                                    $model->setData("size_and_fit", $fitAndSizingCopyBulletField);
                                    $model->setData("fabric_contents", $fabricContentField);
                                    $model->setData("care_instructions", $careInstructionField);
                                    $model->setData("filename", $filename);
                                    $model->setData("is_factory", $is_factory);
                                    $model->setData("status", $vpnDataForImport->status);
                                    $model->setData("color_codes", $colorCodes);

                                    $this->atgVpnModel->updateVpnData($model,'atg_vpn_data');

                                    if (property_exists($vpnDataForImport, 'customerChoice') && ($vpnDataForImport->customerChoice!=null)) {
                                        foreach ($vpnDataForImport->customerChoice as $customerChoice) {
                                            $images = $colorCode = $colorDescription = $baseColorName = '';
                                            if (property_exists($customerChoice, 'images')) {
                                                $images = $customerChoice->images;
                                            }

                                            if (!empty($customerChoice)) {
                                                $in_image = $this->getImages($images, 'in_image');
                                                $bk_image = $this->getImages($images, 'bk_image');
                                                $fr_image = $this->getImages($images, 'fr_image');
                                                $cu_image = $this->getImages($images, 'cu_image');
                                                $pk_image = $this->getImages($images, 'pk_image');
                                                $sw_image = $this->getImages($images, 'sw_image');
                                                $poster_url = $this->getImages($images, 'poster_url');
                                                $video_url = $this->getImages($images, 'video_url');


                                                if (property_exists($customerChoice, 'colorCode')) {
                                                    $colorCode = $customerChoice->colorCode;
                                                }
                                                if (property_exists($customerChoice, 'colorDescription')) {
                                                    $colorDescription = $customerChoice->colorDescription;
                                                }
                                                if (property_exists($customerChoice, 'baseColorName')) {
                                                    $baseColorName = $customerChoice->baseColorName;
                                                }
                                                $modelColor = $this->_atgVpnColorMapping->create();
                                                $modelColor->setData("vpn", $vpnDataForImport->styleNumber);
                                                $modelColor->setData("color_code", $colorCode);
                                                $modelColor->setData("color_description", $colorDescription);
                                                $modelColor->setData("color_name", $baseColorName);
                                                $modelColor->setData("in_image", $in_image);
                                                $modelColor->setData("bk_image", $bk_image);
                                                $modelColor->setData("fr_image", $fr_image);
                                                $modelColor->setData("cu_image", $cu_image);
                                                $modelColor->setData("pk_image", $pk_image);
                                                $modelColor->setData("sw_image", $sw_image);
                                                $modelColor->setData("poster_url", $poster_url);
                                                $modelColor->setData("video_url", $video_url);
                                                $modelColor->setData("status", $vpnDataForImport->status);
                                                $this->atgVpnModel->updateVpnData($modelColor,'atg_vpn_color_mapping');

                                            }
                                        }
                                    }

                                    if (++$rowCount > $batchCount) {
                                        $rowCount = 1;
                                        $modelVpnData->commit();
                                        $batchRowCount++;
                                    }
                                    $count++;
                                    $modelVpnData->commit();
                                }
                            }
                            $this->_logger->addInfo('----Vpn Import Data Process :: VPN : .'.$vpnDataForImport->styleNumber.' processed');
                        }
                        $this->markFileAsProcessed(array($filename), self::GAP_FILE_TYPE);
                    } catch (\Exception $e) {
                        $this->_logger->addInfo('---- Vpn Import Data Process Error :: Error message .'.$e->getMessage());
                        $output->writeln('---- Vpn Import Data Process Error :: Error message .'.$e->getMessage());
                    }
                }

            }
        }catch (\Exception $e)
        {
            $this->_logger->addInfo('---- Vpn Data Process ::Error message .'.$e->getMessage());
            $output->writeln('---- Vpn Data Process ::Error message .'.$e->getMessage());
        }
    }

    /**
     * @param $imagesObj
     * @param $type
     * @return mixed
     */
    public function getImages($imagesObj, $type)
    {
        if(!empty($imagesObj))
        {
                switch($type) {
                    case 'in_image':
                        $in_image = '';
                        foreach($imagesObj as $images) {
                            if (isset($images->Z) && $images->Z != '') {
                                $in_image = $images->Z;
                            } else if (isset($images->VLI) && $images->VLI != '') {
                                $in_image = $images->VLI;
                            } else if (isset($images->AV2_VLI) && $images->AV2_VLI != '') {
                                $in_image = $images->AV2_VLI;
                            } else if (isset($images->AV2_Z) && $images->AV2_Z != '') {
                                $in_image = $images->AV2_Z;
                            }
                        }
                        return $in_image;

                    case 'sw_image':
                        $sw_image = '';
                        foreach($imagesObj as $images) {
                            if (isset($images->S) && $images->S != '') {
                                $sw_image = $images->S;
                            }
                        }
                        return $sw_image;

                    case 'poster_url':
                        $poster_url = '';
                        foreach($imagesObj as $images) {
                            if (isset($images->PRST_IMG) && $images->PRST_IMG != '') {
                                $poster_url = $images->PRST_IMG;
                            }
                        }
                        if($poster_url=='')
                        {
                            foreach($imagesObj as $images) {
                                if (isset($images->AV9_Z) && $images->AV9_Z != '') {
                                    $poster_url = $images->AV9_Z;
                                }
                            }
                        }
                        return $poster_url;

                    case 'video_url':
                        $video_url = '';
                        foreach($imagesObj as $images) {
                            if (isset($images->AV9_VD) && $images->AV9_VD != '') {
                                $video_url = $images->AV9_VD;
                            }
                        }
                        return $video_url;

                    case 'fr_image':
                        $fr_image = '';
                        foreach($imagesObj as $images) {
                            if (isset($images->AV3_Z) && $images->AV3_Z != '') {
                                $fr_image = $images->AV3_Z;
                            } else if (isset($images->VLI) && $images->VLI != '') {
                                $fr_image = $images->VLI;
                            }
                        }
                        return $fr_image;

                    case 'bk_image':
                        $bk_image = '';
                        foreach($imagesObj as $images) {
                            if (isset($images->AV1_Z) && $images->AV1_Z != '') {
                                $bk_image = $images->AV1_Z;
                            } else if (isset($images->AV1_VLI) && $images->AV1_VLI != '') {
                                $bk_image = $images->AV1_VLI;
                            } else if (isset($images->AV1) && $images->AV1 != '') {
                                $bk_image = $images->AV1;
                            } else if (isset($images->AV1_Z) && $images->AV1_Z != '') {
                                $bk_image = $images->AV1_Z;
                            }
                        }
                        return $bk_image;

                    case 'cu_image':
                        $cu_image = '';
                        foreach($imagesObj as $images) {
                            if (isset($images->AV4_Z) && $images->AV4_Z != '') {
                                $cu_image = $images->AV4_Z;
                            } else if (isset($images->AV4_VLI) && $images->AV4_VLI != '') {
                                $cu_image = $images->AV4_VLI;
                            } else if (isset($images->AV3_VLI) && $images->AV3_VLI != '') {
                                $cu_image = $images->AV3_VLI;
                            }
                        }
                        return $cu_image;

                    case 'pk_image':
                        $pk_image = '';
                        foreach($imagesObj as $images) {
                            if (isset($images->AV5_Z) && $images->AV5_Z != '') {
                                $pk_image = $images->AV5_Z;
                            } else if (isset($images->AV5_VLI) && $images->AV5_VLI != '') {
                                $pk_image = $images->AV5_VLI;
                            } else if (isset($images->AV2_Z) && $images->AV2_Z != '') {
                                $pk_image = $images->AV2_Z;
                            } else if (isset($images->AV2_QL) && $images->AV2_QL != '') {
                                $pk_image = $images->AV2_QL;
                            }
                        }
                        return $pk_image;

                    default:
                        break;

                }
        }
    }

    /**
     * @param $stdClass
     * @return array
     */
    function convertStdObjectToArray($stdClass)
    {
        if (!is_array($stdClass))
        {
            $arrayObj = [$stdClass];
        }
        else
        {
            $arrayObj = $stdClass;
        }
        return $arrayObj;
    }

    function convertHtml($str,$returnFieldData)
    {
        foreach ($str as $val)
        {
            if(is_object($val))
            {
                foreach ($val as $key=>$value) {
                    $type = '';
                    if($key == 'percentage')
                    {
                        $type = ' %';
                    }
                    $returnFieldData .= '<p>' . $key . ' : '.$value. $type.'</p>';
                }
            }else {
                $returnFieldData .= '<p>' . $val . '</p>';
            }
        }
        return $returnFieldData;
    }
}
