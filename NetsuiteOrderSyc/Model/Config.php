<?php
/**
 * Copyright © Echidna Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Echidna\NetsuiteOrderSyc\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Framework\Serialize\Serializer\Json;

class Config
{
    /**
     * @var ScopeConfigInterface
     */
    private ScopeConfigInterface $scopeConfig;
    /**
     * @var Json
     */
    protected Json $_json;

    /**
     * @param ScopeConfigInterface $scopeConfig
     * @param Json $json
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        Json                 $json
    )
    {
        $this->scopeConfig = $scopeConfig;
        $this->_json = $json;
    }

    /**
     * @param $websiteId
     * @return bool
     */
    public function isNetsuiteOrderSyscEnabled($websiteId): bool
    {
        return $this->scopeConfig->getValue('echidna_netsuite/netsuite_order_sysc_configration/enabled', ScopeInterface::SCOPE_WEBSITE, $websiteId);
    }

    /**
     * @param $websiteId
     * @return mixed
     */
    public function getCustomFieldScriptId($websiteId)
    {
        return $this->scopeConfig->getValue('echidna_netsuite/netsuite_order_sysc_configration/custom_field_script_id', ScopeInterface::SCOPE_WEBSITE, $websiteId);
    }

    /**
     * @param $websiteId
     * @return mixed
     */
    public function getCustomerInternalId($websiteId)
    {
        return $this->scopeConfig->getValue('echidna_netsuite/netsuite_order_sysc_configration/customer_internal_id', ScopeInterface::SCOPE_WEBSITE, $websiteId);
    }

    /**
     * @param $order
     * @param $websiteId
     * @return array|bool|float|int|mixed|string|null
     */
    public function getShippingMethod($websiteId)
    {
        $shippingMethodJsonData = $this->scopeConfig->getValue('echidna_netsuite/netsuite_order_sysc_configration/netsuite_shipping_method_mapping', ScopeInterface::SCOPE_WEBSITE, $websiteId);
        return $this->_json->unserialize($shippingMethodJsonData);
    }

    /**
     * @param $websiteId
     * @return array|bool|float|int|mixed|string|null
     */
    public function getLocationInternalId($websiteId)
    {
        $shippingMethodJsonData = $this->scopeConfig->getValue('echidna_netsuite/netsuite_order_sysc_configration/netsuite_location_mapping', ScopeInterface::SCOPE_WEBSITE, $websiteId);
        return  $this->_json->unserialize($shippingMethodJsonData);
    }

    /**
     * @param $websiteId
     * @return array|bool|float|int|mixed|string|null
     */
    public function getFixLocationInternalId($websiteId)
    {
        $shippingMethodJsonData = $this->scopeConfig->getValue('echidna_netsuite/netsuite_order_sysc_configration/netsuite_location_fix_internal_id', ScopeInterface::SCOPE_WEBSITE, $websiteId);
        return  $this->_json->unserialize($shippingMethodJsonData);
    }

    /**
     * @param $websiteId
     * @return mixed
     */
    public function getTaxProductInternalId($websiteId)
    {
        return $this->scopeConfig->getValue('echidna_netsuite/netsuite_order_sysc_configration/tax_product_internal_id', ScopeInterface::SCOPE_WEBSITE, $websiteId);
    }

    /**
     * @param $websiteId
     * @return mixed
     */
    public function isTermsOrderSyscEnabled($websiteId)
    {
        return $this->scopeConfig->getValue('echidna_netsuite/netsuite_order_sysc_configration/terms_sync_enabled', ScopeInterface::SCOPE_WEBSITE, $websiteId);
    }

    /**
     * @param $websiteId
     * @return mixed
     */
    public function getTermsInternalId($websiteId)
    {
        return $this->scopeConfig->getValue('echidna_netsuite/netsuite_order_sysc_configration/terms_internal_id', ScopeInterface::SCOPE_WEBSITE, $websiteId);
    }

    /**
     * @param $websiteId
     * @return mixed
     */
    public function getDiscountItemInternalId($websiteId)
    {
        return $this->scopeConfig->getValue('echidna_netsuite/netsuite_order_sysc_configration/discount_item_internal_id', ScopeInterface::SCOPE_WEBSITE, $websiteId);
    }
}
