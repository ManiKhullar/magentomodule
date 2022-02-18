<?php
/**
 * Mappings.php
 * @package   Altayer\Newsletter\Model\Config\Source
 * @author    Ryazuddin <Ryazuddin@altayer.com>
 * @date      07/Jan/2020
 */


namespace Altayer\Support\Model\Config\Source;

use Magento\Framework\Option\ArrayInterface;

class Mappings implements ArrayInterface
{
    /**
     * @var \Altayer\Support\Model\CountryMapping
     */
    protected $_countryMapping = null;

    public function __construct(\Altayer\Support\Model\CountryMapping $countryMapping)
    {
        $this->_countryMapping = $countryMapping;
    }

    /**
     * Return array of options as value-label pairs
     *
     * @return array Format: array(array('value' => '<value>', 'label' => '<label>'), ...)
     */
    public function toOptionArray()
    {
        $value = $this->_countryMapping->getConfigValues();
        $country = [];

        if (empty($value)) {
            return $country;
        }
        $count = 0;
        foreach ($value as $key => $valueLabel) {
            $data = array('value' => $valueLabel, 'label' => __($key));
            $country [$count++] = $data;
        };
        return $country;
    }

    /**
     * @return array|mixed
     */
    public function getRawMapping()
    {
        return $this->_countryMapping->getConfigValues();
    }
}