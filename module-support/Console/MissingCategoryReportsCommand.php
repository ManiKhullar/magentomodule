<?php
/**
 * MissingCategoryReportsCommand.php
 * @package   Altayer\Support\Console
 * @author   Mani <kmanidev6@gmail.com>
 */

namespace Altayer\Support\Console;

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


/**
 * Class MissingCategoryReportsCommand
 * @package Altayer\Support\Console
 */
class MissingCategoryReportsCommand extends Command
{
    const NAME = 'catalog:products:MissingReportCateories';
    
    const MISSING_CATEGORIES_ENABLE = 'altayer_order_monitor/Missing_reports/create_category_reports';
    
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
     * MissingCategoryReportsCommand constructor.
     * @param Utility $utility
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
     */
    public function __construct(
        Utility $utility,
        Csv $csv,
        DirectoryList $directoryList,
        ResourceConnection $resource,
        PsrLogger $logger,
        Helper $helper,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Altayer\Oms\Model\OrderManagement $orderManagement,
        Filesystem $filesystem,
        ObjectManagerInterface $objectManager,
        ProductCollectionFactory $productCollectionFactory,
        \Magento\Catalog\Api\ProductRepositoryInterface $productloader,
        $name = null
    )
    {
        parent::__construct($name);
        $this->csv = $csv;
        $this->directoryList = $directoryList;
        $this->utility = $utility;
        $this->_logger = $logger;
        $this->helper = $helper;
        $this->scopeConfig = $scopeConfig;
        $this->orderManagement = $orderManagement;
        $this->_objectManager = $objectManager;
        $this->filesystem = $filesystem;
        $this->_productCollectionFactory = $productCollectionFactory;
        $this->_productloader = $productloader;
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this->setName(self::NAME);
        $this->setDescription('Missing Category Reports Of Categories');
        parent::configure();
    }

    /**
     * export report orders
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $missingCategoriesEnabled = $this->scopeConfig->getValue(self::MISSING_CATEGORIES_ENABLE);
        if($missingCategoriesEnabled)
        {
            $this->exportMissingReports($output);
        }else
        {
           $output->writeln("Please Enable the Missing Configurations");
        }

    }

    /**
     * @param $output
     */
    public function exportMissingReports($output)
    {
        $exportData = [];
        $exportData[] = $this->getHeaders();
        $outputFile = "UnAssignedProduct";
        $missingReport = $this->getReport($output);
        if (empty($missingReport)) {
            $exportData[] = ["All good and mapped"];
        } else {
            foreach ($this->getReport($output) as $data) {
                $exportData[]= [
                    $data["sku"],
                    $data["parent"],
                    $data["phase_name"],
                    $data["season_name"],
                    $data["dept"],
                    $data["class"],
                    $data["subclass"],
                    $data["vpn"]
                ];
            }
        }
        $this->downloadCsv($outputFile,$exportData,$output); 
      

    }

    /**
     * @param $output
     * @return mixed
     */
    public function getReport($output){

        $sql = "
            SELECT
               p.sku,
               i.parent,
               i.phase_name,
               i.season_name,
               m.dept,
               m.class,
               m.subclass,
               i.vpn,
               p.entity_id 
            FROM
               catalog_product_entity AS p 
               INNER JOIN
                  atg_item_detail AS i 
                  ON p.sku = i.sku 
               INNER JOIN
                  atg_merchandise_hier AS m 
                  ON m.subclass_id = Concat('5:', i.department_id, i.class_id, i.subclass_id) 
            where
               p.entity_id in
               (
                 select product_id from (SELECT
                    product_id,category_id 
                  FROM
                     catalog_category_product 
                  where category_id IN (2, 4, 5, 6, 8, 9, 10) ) as t where t.product_id not in (SELECT
                    product_id 
                  FROM
                     catalog_category_product 
                  where category_id  NOT IN (2, 4, 5, 6, 8, 9, 10)) 
               )
            order by
               p.entity_id desc";
        $connection = $this->_productCollectionFactory->create()->getConnection();
        $reportData = $connection->fetchAll($sql);
        return $reportData;
    }

    /**
     * @param $file
     */
    public function downloadCsv($file,$exportData,$output) 
    {
        $filename = $file;
        $date = (new \DateTime())->format('Y-m-d H:i:s');
        $path = $this->directoryList->getPath('var') . DIRECTORY_SEPARATOR . $filename . "{$date}.csv";
        $this->csv->saveData($path, $exportData);
        $this->helper->sendCategoryMissingReport($output,$filename);
    }

    /**
     * @return array
     */
    public function getHeaders()
    {
        try {
            $heading = [
                __('sku'),
                __('parent'),
                __('phase_name'),
                __('season_name'),
                __('department'),
                __('class'),
                __('subclass'),
                __('vpn')
            ];
            return $heading;
        }catch(\Exception $e){
            $this->_logger->debug($e->getMessage());
        }
      
    }


}
