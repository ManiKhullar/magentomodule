<?php
namespace Altayer\Support\Model;

/**
 * CountryMapping.php
 * @package   Altayer\Support\Model\Config\Source
 * @author    Ryazuddin <Ryazuddin@altayer.com>
 * @date      07/Jan/2020
 */
class CountryMapping
{
    /**
     * Core store config
     *
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * @var \Magento\Framework\Math\Random
     */
    protected $mathRandom;

    const XML_CONFIG_COUNTRY_MAP = 'altayer_order_monitor/order_monitor/country_currency_map';

    /**
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param \Magento\Framework\Math\Random $mathRandom
     */
    public function __construct(
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Framework\Math\Random $mathRandom
    )
    {
        $this->scopeConfig = $scopeConfig;
        $this->mathRandom = $mathRandom;
    }

    /**
     * @param $values
     * @return null
     */
    protected function checkValue($values)
    {
        return !empty($values) ? $values : null;
    }

    /**
     * Generate a storable representation of a value
     *
     * @param int|float|string|array $value
     * @return string
     */
    protected function serializeValue($value)
    {
        if (is_numeric($value)) {
            $data = (int)$value;
            return (string)$data;
        } elseif (is_array($value)) {
            $data = [];
            foreach ($value as $country => $currency) {
                if (!array_key_exists($country, $data)) {
                    $data[$country] = $this->checkValue($currency);
                }
            }
            return serialize($data);
        } else {
            return '';
        }
    }

    /**
     * Create a value from a storable representation
     *
     * @param int|float|string $value
     * @return array
     */
    protected function unserializeValue($value)
    {
        if (is_string($value) && !empty($value)) {
            return unserialize($value);
        } else {
            return [];
        }
    }

    /**
     * Check whether value is in form retrieved by _encodeArrayFieldValue()
     *
     * @param string|array $value
     * @return bool
     */
    protected function isEncodedArrayFieldValue($value)
    {
        if (!is_array($value)) {
            return false;
        }
        unset($value['__empty']);
        foreach ($value as $row) {
            if (!is_array($row)
                || !array_key_exists('country', $row)
                || !array_key_exists('currency', $row)
            ) {
                return false;
            }
        }
        return true;
    }

    /**
     * Encode value to be used in \Magento\Config\Block\System\Config\Form\Field\FieldArray\AbstractFieldArray
     *
     * @param array $value
     * @return array
     */
    protected function encodeArrayFieldValue(array $value)
    {
        $result = [];
        foreach ($value as $country => $currency) {
            $resultId = $this->mathRandom->getUniqueHash('_');
            $result[$resultId] = ['country' => $country, 'currency' => $currency];
        }
        return $result;
    }

    /**
     * Decode value from used in \Magento\Config\Block\System\Config\Form\Field\FieldArray\AbstractFieldArray
     *
     * @param array $value
     * @return array
     */
    protected function decodeArrayFieldValue(array $value)
    {
        $result = [];
        unset($value['__empty']);
        foreach ($value as $row) {
            if (!is_array($row)
                || !array_key_exists('country', $row)
                || !array_key_exists('currency', $row)
            ) {
                continue;
            }
            $country = $row['country'];
            $currency = $this->checkValue($row['currency']);
            $result[$country] = $currency;
        }
        return $result;
    }


    /**
     * @param null $store
     * @return array|mixed
     */
    public function getConfigValues($store = null)
    {
        $value = $this->scopeConfig->getValue(self::XML_CONFIG_COUNTRY_MAP, \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $store);
        $value = $this->unserializeValue($value);
        if ($this->isEncodedArrayFieldValue($value)) {
            $value = $this->decodeArrayFieldValue($value);
        }
        return $value;
    }

    /**
     * Make value readable by \Magento\Config\Block\System\Config\Form\Field\FieldArray\AbstractFieldArray
     *
     * @param string|array $value
     * @return array
     */
    public function makeArrayFieldValue($value)
    {
        $value = $this->unserializeValue($value);
        if (!$this->isEncodedArrayFieldValue($value)) {
            $value = $this->encodeArrayFieldValue($value);
        }
        return $value;
    }

    /**
     * Make value ready for store
     *
     * @param string|array $value
     * @return string
     */
    public function makeStorableArrayFieldValue($value)
    {
        if ($this->isEncodedArrayFieldValue($value)) {
            $value = $this->decodeArrayFieldValue($value);
        }
        $value = $this->serializeValue($value);
        return $value;
    }

}
