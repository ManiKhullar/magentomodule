<?php
/**
 * RegenerateProductRewrites.php
 *
 * @package Altayer_Support
 * @author   Mani <kmanidev6@gmail.com>
 */

namespace Altayer\Support\Model;

use Altayer\Support\Helper\Regenerate as RegenerateHelper;
use Magento\Catalog\Model\ResourceModel\Product\ActionFactory as ProductActionFactory;
use Magento\CatalogUrlRewrite\Model\ProductUrlPathGeneratorFactory;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;

class RegenerateProductRewrites extends AbstractRegenerateRewrites
{
    /**
     * @var string
     */
    protected $entityType = 'product';

    /**
     * @var int
     */
    protected $productsCollectionPageSize = 1000;

    /**
     * @var ProductActionFactory
     */
    protected $productActionFactory;

    /**
     * @var \Magento\Catalog\Model\ResourceModel\Product\Action
     */
    protected $productAction;

    /**
     * @var \Magento\CatalogUrlRewrite\Model\ProductUrlRewriteGenerator
     */
    protected $productUrlRewriteGenerator;

    /**
     * @var ProductUrlPathGeneratorFactory
     */
    protected $productUrlPathGeneratorFactory;

    /**
     * @var \Magento\CatalogUrlRewrite\Model\ProductUrlPathGenerator
     */
    protected $productUrlPathGenerator;

    /**
     * @var ProductCollectionFactoryy
     */
    protected $productCollectionFactory;

    /**
     * RegenerateProductRewrites constructor.
     * @param RegenerateHelper $helper
     * @param ResourceConnection $resourceConnection
     * @param ProductActionFactory $productActionFactory
     * @param ProductUrlPathGeneratorFactory\Proxy $productUrlPathGeneratorFactory
     * @param ProductCollectionFactory $productCollectionFactory
     */
    public function __construct(
        RegenerateHelper $helper,
        ProductActionFactory $productActionFactory,
        ProductUrlPathGeneratorFactory\Proxy $productUrlPathGeneratorFactory,
        ProductCollectionFactory $productCollectionFactory
    )
    {
        parent::__construct($helper);
        $this->productActionFactory = $productActionFactory;
        $this->productUrlPathGeneratorFactory = $productUrlPathGeneratorFactory;
        $this->productCollectionFactory = $productCollectionFactory;
    }

    /**
     * Regenerate Products Url Rewrites in specific store
     * @return $this
     */
    public function regenerate($storeId = 0)
    {
        if (count($this->regenerateOptions['productsFilter']) > 0) {
            $this->regenerateProductsRangeUrlRewrites(
                $this->regenerateOptions['productsFilter'],
                $storeId
            );
        } elseif (!empty($this->regenerateOptions['productId'])) {
            $this->regenerateSpecificProductUrlRewrites(
                $this->regenerateOptions['productId'],
                $storeId
            );
        } else {
            $this->regenerateAllProductsUrlRewrites($storeId);
        }

        return $this;
    }

    /**
     * Regenerate all products Url Rewrites
     * @param int $storeId
     * @return $this
     */
    public function regenerateAllProductsUrlRewrites($storeId = 0)
    {
        $this->regenerateProductsRangeUrlRewrites([], $storeId);

        return $this;
    }

    /**
     * Regenerate Url Rewrites for a specific product
     * @param int $productId
     * @param int $storeId
     * @return $this
     */
    public function regenerateSpecificProductUrlRewrites($productId, $storeId = 0)
    {
        $this->regenerateProductsRangeUrlRewrites([$productId], $storeId);

        return $this;
    }

    /**
     * Regenerate Url Rewrites for a products range
     * @param array $productsFilter
     * @param int $storeId
     * @return $this
     */
    public function regenerateProductsRangeUrlRewrites($productsFilter = [], $storeId = 0)
    {
        $products = $this->_getProductsCollection($productsFilter, $storeId);
        $pageCount = $products->getLastPageNumber();
        $this->progressBarProgress = 1;
        $this->progressBarTotal = (int)$products->getSize();
        $currentPage = 1;

        while ($currentPage <= $pageCount) {
            $products->clear();
            $products->setCurPage($currentPage);

            foreach ($products as $product) {
                $this->_showProgress();
                $this->processProduct($product, $storeId);
            }

            $currentPage++;
        }

        return $this;
    }

    /**
     * Regenerate Url Rewrites for specific product in specific store
     * @param $entity
     * @param int $storeId
     * @return $this
     */
    public function processProduct($entity, $storeId = 0)
    {
        $entity->setStoreId(0)->setData('url_path', null);
        if ($this->regenerateOptions['saveOldUrls']) {
            $entity->setData('save_rewrites_history', true);
        }
        // reset url_path to null, we need this to set a flag to use a Url Rewrites:
        // if "request_path" is not null or equal to "false" then Magento do not serach and do not use Url Rewrites
        $updateAttributes = ['url_path' => null];
        if (!$this->regenerateOptions['noRegenUrlKey']) {
            $generatedKey = $this->_getProductUrlPathGenerator()->getUrlKey($entity->setUrlKey(null));
            $updateAttributes['url_key'] = $generatedKey;
        }

        $this->_getProductAction()->updateAttributes(
            [$entity->getId()],
            $updateAttributes,
            $storeId
        );

        $this->progressBarProgress++;

        return $this;
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

    /**
     * @return \Magento\CatalogUrlRewrite\Model\ProductUrlPathGenerator
     */
    protected function _getProductUrlPathGenerator()
    {
        if (is_null($this->productUrlPathGenerator)) {
            $this->productUrlPathGenerator = $this->productUrlPathGeneratorFactory->create();
        }

        return $this->productUrlPathGenerator;
    }

    /**
     * Get products collection
     * @param array $productsFilter
     * @param int $storeId
     * @return mixed
     */
    protected function _getProductsCollection($productsFilter = [], $storeId = 0)
    {
        $productsCollection = $this->productCollectionFactory->create();

        $productsCollection->setStore($storeId)
            ->addStoreFilter($storeId)
            ->addAttributeToSelect('name')
            ->addAttributeToSelect('gender')
            ->addAttributeToSelect('color')
            ->addAttributeToSelect('visibility')
            ->addAttributeToSelect('url_key')
            ->addAttributeToSelect('url_path')
            // use limit to avoid a "eating" of a memory
            ->setPageSize($this->productsCollectionPageSize);

        if (count($productsFilter) > 0) {
            $productsCollection->addIdFilter($productsFilter);
        }

        return $productsCollection;
    }
}
