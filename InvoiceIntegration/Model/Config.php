<?php
/**
 * Created by PhpStorm.
 * User: Nandisha N
 * Date: 23/12/21
 * Time: 9:26 PM
 */

namespace Echidna\InvoiceIntegration\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;

class Config
{
    const IS_ENABLED = 'echidna_cb_configuration/enable_customer_price_integration_configuration/enable_customer_price_integration';

    private StoreManagerInterface $storeManager;
    private ScopeConfigInterface $scopeConfig;

    public function __construct(
        ScopeConfigInterface  $scopeConfig,
        StoreManagerInterface $storeManager
    ) {
        $this->storeManager = $storeManager;
        $this->scopeConfig = $scopeConfig;
    }

    /**
     * @return string
     * @throws LocalizedException
     */
    public function getCurrentWebsiteCode(): string
    {
        return $this->storeManager->getWebsite()->getCode();
    }

    /**
     * @return int|null
     */
    public function getStoreId(): ?int
    {
        try {
            return $this->storeManager->getStore()->getId();
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * @return bool|null
     */
    public function isCustomPriceOnStoreFrontEnable(): ?bool
    {
        try {
            $storeId = $this->getStoreId();
            $value = $this->scopeConfig->getValue(
                self::IS_ENABLED,
                ScopeInterface::SCOPE_STORE,
                $storeId
            );
            return (bool)$value;
        } catch (\Exception $e) {
            return false;
        }
    }
}
