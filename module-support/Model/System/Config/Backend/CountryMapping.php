<?php

namespace Altayer\Support\Model\System\Config\Backend;

/**
 * CountryMapping.php
 * @package   Altayer\Support\Model\Config\Source
 * @author    Ryazuddin <Ryazuddin@altayer.com>
 * @date      07/Jan/2020
 */


class CountryMapping extends \Magento\Framework\App\Config\Value
{
    /**
     * @var \Altayer\Support\Model\CountryMapping
     */
    protected $_countryMapping = null;

    /**
     * CountryMapping constructor.
     * @param \Magento\Framework\Model\Context $context
     * @param \Magento\Framework\Registry $registry
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $config
     * @param \Magento\Framework\App\Cache\TypeListInterface $cacheTypeList
     * @param \Altayer\Support\Model\CountryMapping $countryMapping
     * @param \Magento\Framework\Model\ResourceModel\AbstractResource|null $resource
     * @param \Magento\Framework\Data\Collection\AbstractDb|null $resourceCollection
     * @param array $data
     */
    public function __construct(
        \Magento\Framework\Model\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\App\Config\ScopeConfigInterface $config,
        \Magento\Framework\App\Cache\TypeListInterface $cacheTypeList,
        \Altayer\Support\Model\CountryMapping $countryMapping,
        \Magento\Framework\Model\ResourceModel\AbstractResource $resource = null,
        \Magento\Framework\Data\Collection\AbstractDb $resourceCollection = null,
        array $data = []
    )
    {
        $this->_countryMapping = $countryMapping;
        parent::__construct($context, $registry, $config, $cacheTypeList, $resource, $resourceCollection, $data);
    }

    /**
     * Process data after load
     *
     * @return void
     */
    protected function _afterLoad()
    {
        $value = $this->getValue();
        $value = $this->_countryMapping->makeArrayFieldValue($value);
        $this->setValue($value);
    }

    /**
     * Prepare data before save
     *
     * @return void
     */
    public function beforeSave()
    {
        $value = $this->getValue();
        $value = $this->_countryMapping->makeStorableArrayFieldValue($value);
        $this->setValue($value);
    }
}
