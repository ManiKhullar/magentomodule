<?php
/**
 * ProductUrlPathGenerator.php
 *
 * @package Altayer_Support
 * @author   Mani <kmanidev6@gmail.com>
 */

namespace Altayer\Support\Model;

use Magento\Eav\Model\Entity\Attribute\AbstractAttribute;

class ProductUrlPathGenerator extends \Magento\CatalogUrlRewrite\Model\ProductUrlPathGenerator
{
    CONST URL_REWRITE_ENABLE = "altayer_order_monitor/url_rewrite_config/url_rewrite_enable";
    CONST URL_CONFIGURATION = "altayer_order_monitor/url_rewrite_config/url_configuration";
    CONST USE_SKU = "altayer_order_monitor/url_rewrite_config/url_configuration";
    CONST URL_KEY_FOR_ADULT = "altayer_order_monitor/url_rewrite_config/url_key_for_adult";
    CONST URL_KEY_FOR_KIDS = "altayer_order_monitor/url_rewrite_config/url_key_for_kids";
    const EAV_ENTITY_TYPE_PRODUCT = 4;
    CONST DEFAULT_STORE_ID = 1;
    CONST ADMIN_STORE_ID = 0;
    CONST UNISEX = 'unisex';

    /** @var \Altayer\Integration\Helper\Data */
    protected $helper;

    /** @var \Magento\Eav\Api\AttributeSetRepositoryInterface $attributeSet **/
    protected $attributeSet;
    protected $_attributeCodeToSelectType = [];
    protected $_attributeCodeToAttribute = [];
    protected $_attributeFactory;

    /**
     * @var \Magento\Catalog\Api\ProductAttributeRepositoryInterface
     */
    protected $attributeRepository;

    protected $swatchHelper;
    /**
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param CategoryUrlPathGenerator $categoryUrlPathGenerator
     * @param \Magento\Catalog\Api\ProductRepositoryInterface $productRepository
     */
    public function __construct(
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\CatalogUrlRewrite\Model\CategoryUrlPathGenerator $categoryUrlPathGenerator,
        \Magento\Catalog\Api\ProductRepositoryInterface $productRepository,
        \Altayer\Support\Helper\Regenerate $helper,
        \Magento\Eav\Api\AttributeSetRepositoryInterface $attributeSet,
        \Magento\Eav\Model\Entity\AttributeFactory $attributeFactory,
        \Magento\Catalog\Api\ProductAttributeRepositoryInterface $attributeRepository,
        \Magento\Swatches\Helper\Data $swatchHelper
    ) {
        parent::__construct($storeManager, $scopeConfig, $categoryUrlPathGenerator, $productRepository);
        $this->helper = $helper;
        $this->attributeSet = $attributeSet;
        $this->_attributeFactory = $attributeFactory;
        $this->attributeRepository = $attributeRepository;
        $this->swatchHelper = $swatchHelper;
    }

    protected function prepareProductUrlKey(\Magento\Catalog\Model\Product $product)
    {
        if ($this->helper->getUrlRewiteConfig(self::URL_REWRITE_ENABLE)){
            $urlKeyAttributeCodes = $this->getUrlKeyAtributeCodes();
            $urlKeyUseSku = $this->isUseSkuForUrlKey();
            $modifiedUrlKey = "";
            $attributeCodeValues = [];
            $attributeSetRepository = $this->attributeSet->get($product->getAttributeSetId());
            // getting the product attribute set name to compare if product gender is unisex
            $attrubuteSetName = $attributeSetRepository->getAttributeSetName();
            foreach ($urlKeyAttributeCodes as $attributeCode) {
                if ($attributeCode == null && $attributeCode == "") {
                    continue;
                }
                $attribute = $this->getProductTypeAttributeByAttributeCode($attributeCode);
                if ($this->_attributeCodeToSelectType[$attributeCode]) {
                    $attributeValue = $product->getData($attributeCode);
                    if ($attributeCode != 'color'){
                        $attributeCodeValues[] = $this->getAttributeValue($attribute, $attributeValue);
                    }else{
                        $attributeCodeValues[] = $this->getAtributeSwatchValue($product, $attributeCode ,$attributeValue);
                    }
                } else {
                    $attributeCodeValues[] = $product->getData($attributeCode);
                }
            }
            // If product gender is unisex if this product is for adult we need to add (men and woman) in the url instead of unisex
            // If product gender is unisex if this product is for kids we need to add (boys and girls) in the url instead of unisex
            // If product gender is unisex and product attribute set is default then we are not able to determine this product is for adult or for kids so adding the unisex in the url
            $urlKeyArray = [];
            foreach ($attributeCodeValues as $attributeValue){
                if (strtolower($attributeValue) == self::UNISEX){
                    if (strpos(strtolower($attrubuteSetName), 'default') !== false){
                        $urlKeyArray[] = $attributeValue;
                    }elseif (strpos(strtolower($attrubuteSetName), 'adult') !== false){
                        $urlKeyArray[] = $this->helper->getUrlRewiteConfig(self::URL_KEY_FOR_ADULT);
                    }else{
                        $urlKeyArray[] = $this->helper->getUrlRewiteConfig(self::URL_KEY_FOR_KIDS);
                    }
                }else{
                    $urlKeyArray[] = $attributeValue;
                }
            }
            $modifiedUrlKey = implode("-", $urlKeyArray);
            if ($urlKeyUseSku && (strlen($modifiedUrlKey) > 0)) {
                $modifiedUrlKey = $modifiedUrlKey . '-' . $product->getSku();
            }
            $url = $this->slugify($modifiedUrlKey);
            $urlKey = $product->getUrlKey();
            return $product->formatUrlKey($urlKey === '' || $urlKey === null ? $url : $urlKey);
        }else{
            $urlKey = $product->getUrlKey();
            return $product->formatUrlKey($urlKey === '' || $urlKey === null ? $product->getName() : $urlKey);
        }
    }

    protected function getUrlKeyAtributeCodes()
    {
        return explode(',',$this->helper->getUrlRewiteConfig(self::URL_CONFIGURATION));
    }

    protected function isUseSkuForUrlKey()
    {
        return $this->helper->getUrlRewiteConfig(self::USE_SKU);
    }

    /**
     * @param $attributeCode
     * @return AbstractAttribute
     */
    protected function getProductTypeAttributeByAttributeCode($attributeCode)
    {
        if (!in_array($attributeCode, $this->_attributeCodeToAttribute)) {
            $attribute = $this->_attributeFactory->create()->loadByCode(self::EAV_ENTITY_TYPE_PRODUCT, $attributeCode);
            $this->_attributeCodeToAttribute[$attributeCode] = $attribute;
            if ($attribute->getFrontendInput() == 'select') {
                $this->_attributeCodeToSelectType[$attributeCode] = true;
            } else {
                $this->_attributeCodeToSelectType[$attributeCode] = false;
            }
        }
        return $this->_attributeCodeToAttribute[$attributeCode];
    }

    /**
     * Return the attribute value based on attribute and the attribute id.
     *
     * @param $pAttribute
     * @param $attributeId
     * @param $storeId
     * @return string
     */
    public function getAttributeValue($pAttribute, $attributeId, $storeId = 0)
    {
        $attributeValue = "";
        $attribute = $this->attributeRepository->get($pAttribute);
        $attribute->setStoreId($storeId);
        $attributeOptionValues = $attribute->getOptions();
        foreach ($attributeOptionValues as $option) {
            if (strtolower($option['value']) == strtolower($attributeId)) {
                $attributeValue = $option['label'];
                break;
            }
        }
        return $attributeValue;
    }

    public function getAtributeSwatchValue($product, $attributeCode, $optionId)
    {
        $isAttributeExist = $product->getResource()->getAttribute($attributeCode);
        $optionText = '';
        if ($isAttributeExist && $isAttributeExist->usesSource()) {
            $optionText = $isAttributeExist->setStoreId(self::DEFAULT_STORE_ID)->getSource()->getOptionText($optionId);
            if (empty($optionText)){
                $optionText = $isAttributeExist->setStoreId(self::ADMIN_STORE_ID)->getSource()->getOptionText($optionId);
            }
        }
        return $optionText;
    }

    protected function slugify($text)
    {
        // replace non letter or digits by -
        $text = preg_replace('~[^\pL\d]+~u', '-', $text);

        // transliterate
        $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);

        // remove unwanted characters
        $text = preg_replace('~[^-\w]+~', '', $text);

        // trim
        $text = trim($text, '-');

        // remove duplicate -
        $text = preg_replace('~-+~', '-', $text);

        // lowercase
        $text = strtolower($text);

        if (empty($text)) {
            return 'n-a';
        }

        return $text;
    }
}
