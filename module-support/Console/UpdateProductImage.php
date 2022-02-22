<?php
/**
 * UpdateProductCopyAttribute.php
 * @package   Altayer\Support\Console
 * @author   Mani <kmanidev6@gmail.com>
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
class UpdateProductImage extends Command
{
    const NAME = 'catalog:products:UpdateProductImage';

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
     * UpdateProductImage constructor.
     * @param PsrLogger $logger
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param HelperData $helperData
     * @param \Magento\Catalog\Model\ProductFactory $productFactory
     * @param CollectionFactory $itemCollectionFactory
     * @param ResourceConnection $resource
     * @param UDACollectionFactory $udaCollectionFactory
     * @param ImportUtility $utility
     * @param \Altayer\OrderExport\Api\LockInterfaceFactory $lockFileFactory
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     * @param null $name
     */
    public function __construct(
        PsrLogger $logger,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        HelperData $helperData,
        \Magento\Catalog\Model\ProductFactory $productFactory,
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
        $this->_productFactory = $productFactory;
        $this->_itemCollectionFactory = $itemCollectionFactory;
        $this->resource = $resource;
        $this->_udaCollectionFactory = $udaCollectionFactory;
        $this->_lockFile = $lockFileFactory;
        $this->_utility = $utility;
        $this->_storeManager = $storeManager;

    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this->setName(self::NAME);
        $this->setDescription('Update Sku Images');
        parent::configure();
    }

    /**
     * export report orders
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $productImages = $this->getProductImages();
        $this->updateProductImage($productImages);


    }

    public function getProductImages()
    {
        $connection = $this->resource->getConnection();
        $sql = "SELECT e.entity_id AS 'id',
       e.sku AS 'sku',
       v2.value AS 'image',
       v3.value AS 'thumbnail',
       v4.value AS 'small_image',
       mg.value AS 'media_gallery',
       mg.store_id AS 'store_id',
       mg.position,
       v2.attribute_id AS image_attribute,
       v3.attribute_id AS thumbnail_attribute,
       v4.attribute_id AS small_image_attribute,
       mg.attribute_id AS media_gallery_attribute

FROM catalog_product_entity e
LEFT JOIN catalog_product_entity_varchar v2 ON e.entity_id = v2.entity_id
AND v2.store_id = 0
AND v2.attribute_id =
  (SELECT attribute_id
   FROM eav_attribute
   WHERE attribute_code = 'image'
     AND entity_type_id =
       (SELECT entity_type_id
        FROM eav_entity_type
        WHERE entity_type_code = 'catalog_product'))
LEFT JOIN catalog_product_entity_varchar v3 ON e.entity_id = v3.entity_id
AND v3.store_id = 0
AND v3.attribute_id =
  (SELECT attribute_id
   FROM eav_attribute
   WHERE attribute_code = 'thumbnail'
     AND entity_type_id =
       (SELECT entity_type_id
        FROM eav_entity_type
        WHERE entity_type_code = 'catalog_product'))
LEFT JOIN catalog_product_entity_varchar v4 ON e.entity_id = v4.entity_id
AND v4.store_id = 0
AND v4.attribute_id =
  (SELECT attribute_id
   FROM eav_attribute
   WHERE attribute_code = 'small_image'
     AND entity_type_id =
       (SELECT entity_type_id
        FROM eav_entity_type
        WHERE entity_type_code = 'catalog_product'))
LEFT JOIN
  (SELECT m1.value_id AS m1_value_id, m2.value, m2.value_id AS m2_value_id, m1.entity_id AS m1_row_id,m1.store_id,m1.position,m2.attribute_id
   FROM catalog_product_entity_media_gallery_value m1
   INNER JOIN catalog_product_entity_media_gallery m2 ON m2.value_id = m1.value_id
   AND m1.store_id = 0
   AND m2.attribute_id =
     (SELECT attribute_id
      FROM eav_attribute
      WHERE attribute_code = 'media_gallery'
        AND entity_type_id =
          (SELECT entity_type_id
           FROM eav_entity_type
           WHERE entity_type_code = 'catalog_product'))
   ) mg ON e.entity_id = m1_row_id
WHERE e.sku IN ('209979562');" ;
        $result = $connection->fetchAll($sql);
        $i = 0;
        foreach($result as $images)
        {
            if($i==0){
                $productImage['image']['image'] = $images['image'];
                $productImage['image']['attribute'] = $images['image_attribute'];

                $productImage['thumbnail']['image'] = $images['thumbnail'];
                $productImage['thumbnail']['attribute'] = $images['thumbnail_attribute'];

                $productImage['small_image']['image'] = $images['small_image'];
                $productImage['small_image']['attribute'] = $images['small_image_attribute'];
            }

            $productImage['media_gallery'][$i]['position'] = $images['position'];
            $productImage['media_gallery'][$i]['store_id'] = $images['store_id'];
            $productImage['media_gallery'][$i]['media_gallery'] = $images['media_gallery'];
            $productImage['media_gallery'][$i]['attribute'] = $images['media_gallery_attribute'];
            $i++;
        }
        return $productImage;
    }

    public function updateProductImage($image)
    {
        if(array_key_exists('image',$image)){
            print_r($image['image']);
        }
        if(array_key_exists('thumbnail',$image)){
            print_r($image['thumbnail']);
        }
        if(array_key_exists('small_image',$image)){
            print_r($image['small_image']);
        }
        if(array_key_exists('media_gallery',$image)){
            print_r($image['media_gallery']);
        }
    }

}
