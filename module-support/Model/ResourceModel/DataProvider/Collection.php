<?php

/**
 * @author Ryazuddin
 * @package Altayer_Support
 * @date 16/08/2020
 * */

namespace Altayer\Support\Model\ResourceModel\DataProvider;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;


class Collection extends AbstractCollection
{
    /**
     * Define model & resource model
     */
    const YOUR_TABLE = 'atg_vpn_data';

    public function __construct(
        \Magento\Framework\Data\Collection\EntityFactoryInterface $entityFactory,
        \Psr\Log\LoggerInterface $logger,
        \Magento\Framework\Data\Collection\Db\FetchStrategyInterface $fetchStrategy,
        \Magento\Framework\Event\ManagerInterface $eventManager,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Framework\DB\Adapter\AdapterInterface $connection = null,
        \Magento\Framework\Model\ResourceModel\Db\AbstractDb $resource = null
    ) {
        $this->_init('Altayer\Support\Model\DataProvider', 'Altayer\Support\Model\ResourceModel\DataProvider');

        parent::__construct(
            $entityFactory, $logger, $fetchStrategy, $eventManager, $connection,
            $resource
        );
        $this->storeManager = $storeManager;
    }
    protected function _initSelect()
    {
        parent::_initSelect();

//        $this->getSelect()->joinLeft(
//            ['enrichment' => $this->getTable('altayer_item_enrichment')],
//            'enrichment.vpn = main_table.vpn and enrichment.status="FAILED"',
//            ['enrichment.status as enrichmentStatus']
//        );
        $this->getSelect()->joinInner(
            ['mapping' => $this->getTable('atg_vpn_color_mapping')],
            'main_table.vpn = mapping.vpn',
            ['Concat(mapping.in_image," ",mapping.bk_image," ",mapping.sw_image," ",mapping.pk_image," ",mapping.fr_image," ",mapping.cu_image," ",mapping.video_url) as image','mapping.color_code as colorCode']
        );

        $this->addFilterToMap('vpn', 'main_table.vpn');
        $this->addFilterToMap('name', 'main_table.name');
        $this->addFilterToMap('description', 'main_table.description');
        $this->addFilterToMap('is_factory', 'main_table.is_factory');
        $this->addFilterToMap('colorCode', 'mapping.color_code');
    }
}