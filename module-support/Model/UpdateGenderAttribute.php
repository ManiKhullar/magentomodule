<?php


namespace Altayer\Support\Model;

use Magento\Framework\App\ResourceConnection;
use Magento\Catalog\Model\ResourceModel\Product\ActionFactory as ProductActionFactory;

class UpdateGenderAttribute
{
    /**
     * @var ResourceConnection
     */
    protected $resourceConnection;

    /**
     * @var ProductActionFactory
     */
    protected $productActionFactory;

    /**
     * @var \Magento\Catalog\Model\ResourceModel\Product\Action
     */
    protected $productAction;

    public function __construct(
        ResourceConnection $resourceConnection,
        ProductActionFactory $productActionFactory
    )
    {
        $this->resourceConnection = $resourceConnection;
        $this->productActionFactory = $productActionFactory;
    }

    public function updateGenderAttribute($output)
    {
        $connection = $this->_getResourceConnection()->getConnection();
        $sql = "select  cpe.entity_id, group_concat( distinct c.value) as value, group_concat(c.store_id) as store_id from catalog_product_entity_int as c
                JOIN catalog_product_entity cpe on c.entity_id = cpe.entity_id
                where c.attribute_id = 275 group by cpe.sku having LENGTH(value) > 4";
        $results = $connection->fetchAll($sql);
        if (count($results) > 1){
            foreach ($results as $result){
                $entity_id = $result['entity_id'];
                $value = explode(',', $result['value']);
                $gender = $value[0];
                $store_id = explode(',', $result['store_id']);
                $store = $store_id[1];
                $updateAttributes = ['gender' => $gender];
                $this->_getProductAction()->updateAttributes(
                    [$entity_id],
                    $updateAttributes,
                    $store
                );
            }
        }else{
            $output->writeln("No Records Found to Update the Gender Attribute Value");
        }
        return $this;
    }

    /**
     * Return resource connection
     * @return ResourceConnection
     */
    protected function _getResourceConnection()
    {
        return $this->resourceConnection;
    }

    /**
     * @return \Magento\Catalog\Model\ResourceModel\Product\Action
     */
    protected function _getProductAction()
    {
        if (is_null($this->productAction)) {
            $this->productAction = $this->productActionFactory->create();
        }

        return $this->productAction;
    }
}