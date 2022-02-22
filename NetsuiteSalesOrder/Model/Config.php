<?php

namespace Echidna\NetsuiteSalesOrder\Model;

use Echidna\Netsuite\Model\Config as NetsuiteConfig;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Store\Api\Data\WebsiteInterface;
use Magento\Store\Api\WebsiteRepositoryInterface;
use Magento\Store\Model\ResourceModel\Store\CollectionFactory;
use Magento\Store\Model\ScopeInterface;

/**
 * Retrieve all the configurations related to NetSuite API
 */
class Config
{
    const MODULE_NAME = 'Echidna_NetsuiteSalesOrder';
    private NetsuiteConfig $netsuiteConfig;
    private ScopeConfigInterface $scopeConfig;
    private WebsiteRepositoryInterface $websiteRepository;
    /**
     * @var null|WebsiteInterface[]
     */
    private ?array $websiteList = null;
    private CollectionFactory $storeCollectionFactory;
    private Json $jsonSerializer;

    /**
     * @param ScopeConfigInterface $scopeConfig
     * @param NetsuiteConfig $netsuiteConfig
     * @param WebsiteRepositoryInterface $websiteRepository
     * @param CollectionFactory $storeCollectionFactory
     * @param Json $jsonSerializer
     */
    public function __construct(
        ScopeConfigInterface       $scopeConfig,
        NetsuiteConfig             $netsuiteConfig,
        WebsiteRepositoryInterface $websiteRepository,
        CollectionFactory          $storeCollectionFactory,
        Json                       $jsonSerializer
    )
    {
        $this->scopeConfig = $scopeConfig;
        $this->netsuiteConfig = $netsuiteConfig;
        $this->websiteRepository = $websiteRepository;
        $this->storeCollectionFactory = $storeCollectionFactory;
        $this->jsonSerializer = $jsonSerializer;
    }

    /**
     * Check if the Netsuite Order sync enabled for the website
     * @param string $storeScope
     * @param null|string|int $scopeCode
     * @return bool
     */
    public function isOrderSyncEnabled(string $storeScope = ScopeInterface::SCOPE_STORE, $scopeCode = null): bool
    {
        return $this->netsuiteConfig->isActive($storeScope, $scopeCode)
            && $this->scopeConfig->getValue('echidna_netsuite/order/cron_enable', $storeScope, $scopeCode);
    }

    /**
     * Check if the Netsuite Order sync enabled for the website
     * @param string $storeScope
     * @param null|string|int $scopeCode
     * @return bool
     */
    public function isFulfillmentSyncEnabled(string $storeScope = ScopeInterface::SCOPE_STORE, $scopeCode = null): bool
    {
        return $this->netsuiteConfig->isActive($storeScope, $scopeCode)
            && $this->scopeConfig->getValue('echidna_netsuite/fulfillment/cron_enable', $storeScope, $scopeCode);
    }

    /**
     * Check if the Netsuite Order sync enabled for the website
     * @param string $storeScope
     * @param null|string|int $scopeCode
     * @return int
     */
    public function getOrderBatchSize(string $storeScope = ScopeInterface::SCOPE_STORE, $scopeCode = null): int
    {
        $value = $this->scopeConfig->getValue('echidna_netsuite/order/batch_size', $storeScope, $scopeCode);
        return $value ?? 50;
    }

    /**
     * Get the
     * @param string $storeScope
     * @param null|string|int $scopeCode
     * @return int
     */
    public function getFulfillmentBatchSize(string $storeScope = ScopeInterface::SCOPE_STORE, $scopeCode = null): int
    {
        $value = $this->scopeConfig->getValue('echidna_netsuite/fulfillment/batch_size', $storeScope, $scopeCode);
        return $value ?? 50;
    }

    /**
     * Get the log status
     * @param string $storeScope
     * @param null|string|int $scopeCode
     * @return bool
     */
    public function isLogEnabled(string $storeScope = ScopeInterface::SCOPE_STORE, $scopeCode = null): bool
    {
        return $this->netsuiteConfig->isLoggingEnabled($storeScope, $scopeCode);
    }

    /**
     * Get the list of Inventory Source mapping
     * @param string $storeScope
     * @param null|string|int $scopeCode
     * @return array
     */
    public function getSourceMapping(string $storeScope = ScopeInterface::SCOPE_STORE, $scopeCode = null): array
    {
        $mapping = $this->scopeConfig->getValue('echidna_netsuite/inventory_source/netsuite_mapping', $storeScope, $scopeCode);
        return $this->jsonSerializer->unserialize($mapping);
    }

    /**
     * Get the Magento's source_code by Netsuite's location value
     * @param string $location
     * @param string $storeScope
     * @param null|string|int $scopeCode
     * @return string
     */
    public function getSourceCodeByNetsuiteLocation(string $location, string $storeScope = ScopeInterface::SCOPE_STORE, $scopeCode = null): string
    {
        $mapping = $this->getSourceMapping($storeScope, $scopeCode);
        foreach ($mapping as $source) {
            if ($source['netsuite_location'] === $location) {
                return $source['source_code'];
            }
        }
        return 'default';
    }

    /**
     * Get the list of Websites the NetSuite Sales Order sync is enabled
     * @return array
     */
    public function getStoresForOrderSync(): array
    {
        $websiteList = $this->getWebsiteList();
        $websites = [];
        foreach ($websiteList as $website) {
            $websiteId = $website->getWebsiteId();
            if ($this->isOrderSyncEnabled(ScopeInterface::SCOPE_WEBSITE, $websiteId)) {
                $websites[] = $websiteId;
            }
        }
        return $this->getStoreIdsByWebsiteIds($websites);
    }

    /**
     * Get the list of Websites the NetSuite Order Item Fulfillment sync is enabled
     * @return array
     */
    public function getStoresForFulfillmentSync(): array
    {
        $websiteList = $this->getWebsiteList();
        $websites = [];
        foreach ($websiteList as $website) {
            $websiteId = $website->getWebsiteId();
            if ($this->isFulfillmentSyncEnabled(ScopeInterface::SCOPE_WEBSITE, $websiteId)) {
                $websites[] = $websiteId;
            }
        }
        return $this->getStoreIdsByWebsiteIds($websites);
    }

    /**
     * @return WebsiteInterface[]
     */
    private function getWebsiteList(): array
    {
        if ($this->websiteList === null) {
            $this->websiteList = $this->websiteRepository->getList();
        }
        return $this->websiteList;
    }

    /**
     * @param array $websiteIds
     * @return array
     */
    private function getStoreIdsByWebsiteIds(array $websiteIds): array
    {
        $collection = $this->storeCollectionFactory->create();
        $collection->addWebsiteFilter($websiteIds);
        return $collection->getAllIds();
    }
}
