<?php
/**
 * Regenerate.php
 *
 * @package Altayer_Support
 * @author   Mani <kmanidev6@gmail.com>
 */

namespace Altayer\Support\Helper;

use Magento\Framework\App\Helper\Context;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;

class Regenerate extends \Magento\Framework\App\Helper\AbstractHelper
{
    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * Regenerate constructor.
     * @param Context $context
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        Context $context,
        StoreManagerInterface $storeManager
    )
    {
        parent::__construct($context);
        $this->storeManager = $storeManager;
        $this->scopeConfig = $context->getScopeConfig();
    }

    /**
     * Get store manager
     * @return StoreManagerInterface
     */
    public function getStoreManager()
    {
        return $this->storeManager;
    }

    /**
     * Get config value of "Use Categories Path for Product URLs" config option
     * @param  mixed $storeId
     * @return boolean
     */
    public function useCategoriesPathForProductUrls($storeId = null)
    {
        return (bool) $this->scopeConfig->getValue(
            'catalog/seo/product_use_categories',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORES,
            $storeId
        );
    }

    /**
     * Clear request path
     * @param  string $requestPath
     * @return string
     */
    protected function _clearRequestPath($requestPath)
    {
        return str_replace(['//', './'], ['/', '/'], ltrim(ltrim($requestPath, '/'), '.'));
    }

    public function getUrlRewiteConfig($configPath)
    {
        return $this->scopeConfig->getValue($configPath);
    }
}
