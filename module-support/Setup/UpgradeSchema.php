<?php
/**
 * @author   Mani <kmanidev6@gmail.com>
 * @package Altayer_support
 * */

namespace Altayer\Support\Setup;

use Magento\Framework\DB\Ddl\Table;
use Magento\Framework\Setup\UpgradeSchemaInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use Magento\Framework\Setup\ModuleContextInterface;

/**
 *  Creating the atg_marketing table
 * */
class UpgradeSchema implements UpgradeSchemaInterface
{
    /** @var SchemaSetupInterface */
    private $setup;

    public function upgrade(SchemaSetupInterface $setup, ModuleContextInterface $context)
    {
        $this->setup = $setup;
        $this->setup->startSetup();
        if (version_compare($context->getVersion(), '1.0.1', '<')) {
            $this->addAtgMarketingTable();
        }
        if (version_compare($context->getVersion(), '1.0.2', '<')) {
            $this->createATGReminderTable();
        }

        if (version_compare($context->getVersion(), '1.0.5', '<')) {
            $this->createAtgVpnDataTable();
            $this->createAtgVpnColorMappingTable();
        }
        $this->setup->endSetup();
    }

    /**
     * This is for marketing purpose to send old quote details
     * @throws \Zend_Db_Exception
     */
    protected function addAtgMarketingTable()
    {
        $atgMarketingTable = $this->setup->getConnection()->newTable(
            $this->setup->getTable('atg_marketing')
        )->addColumn(
            'quote_id',
            \Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
            null,
            [
                'identity' => true,
                'unsigned' => true,
                'nullable' => false,
                'primary' => true
            ],
            'Quote Id'
        )->addColumn(
            'platform',
            \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
            30,
            [],
            'Platform'
        )->addColumn(
            'checkout_success',
            \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
            10,
            ['nullable' => false, 'default' => 'false'],
            'Checkout Success'
        )->addColumn(
            'coupon_code',
            \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
            30,
            [],
            'Coupon Code'
        )->addColumn(
            'customer_email',
            \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
            50,
            [],
            'Customer Email Id'
        )->addColumn(
            'customer_title',
            \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
            10,
            [],
            'Customer Title'
        )->addColumn(
            'customer_name',
            \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
            30,
            [],
            'Custome Name'
        )->addColumn(
            'customer_phone',
            \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
            20,
            [],
            'Customer Phone'
        )->addColumn(
            'customer_locale',
            \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
            20,
            [],
            'Customer Locale'
        )->addColumn(
            'currency',
            \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
            10,
            [],
            'Currency'
        )->addColumn(
            'cart_details',
            \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
            '64k',
            [],
            'Customer Cart Details'
        )->addColumn(
            'order_id',
            \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
            30,
            [],
            'Order ID'
        )->addColumn(
            'order_placed_on_device',
            \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
            10,
            [],
            'Order Placed Device'
        )->addColumn(
            'applied_coupon_code_on_order',
            \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
            10,
            [],
            'Applied Coupon on order'
        )->addColumn(
            'order_created_on',
            \Magento\Framework\DB\Ddl\Table::TYPE_DATETIME,
            null,
            ['nullable' => true],
            'Order Created Date'
        )->addColumn(
            'created_on',
            \Magento\Framework\DB\Ddl\Table::TYPE_TIMESTAMP,
            null,
            ['nullable' => false, 'default' => Table::TIMESTAMP_INIT],
            'Created On'
        )->addColumn(
            'updated_on',
            Table::TYPE_TIMESTAMP,
            null,
            ['nullable' => false, 'default' => Table::TIMESTAMP_UPDATE],
            'Update On'
        )->setComment(
            'ATG Marketing Table'
        );

        $this->setup->getConnection()->createTable($atgMarketingTable);

    }

    /**
     * This table is used to keep the history of Reminder data that sent to salesforce
     * @throws \Zend_Db_Exception
     */
    protected function createATGReminderTable()
    {
        $atgMarketingTable = $this->setup->getConnection()->newTable(
            $this->setup->getTable('atg_reminder')
        )->addColumn(
            'quote_id',
            \Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
            null,
            [
                'identity' => true,
                'unsigned' => true,
                'nullable' => false,
                'primary' => true
            ],
            'Quote Id'
        )->addColumn(
            'platform',
            \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
            30,
            ['nullable' => false, 'default' => 'web'],
            'Platform'
        )->addColumn(
            'checkout_success',
            \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
            10,
            ['nullable' => false, 'default' => 'false'],
            'Checkout Success'
        )->addColumn(
            'coupon_code',
            \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
            30,
            [],
            'Coupon Code'
        )->addColumn(
            'contact_id',
            \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
            30,
            [],
            'Contact Id'
        )->addColumn(
            'customer_email',
            \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
            50,
            [],
            'Customer Email Id'
        )->addColumn(
            'customer_title',
            \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
            10,
            [],
            'Customer Title'
        )->addColumn(
            'customer_name',
            \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
            30,
            [],
            'Custome Name'
        )->addColumn(
            'customer_phone',
            \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
            20,
            [],
            'Customer Phone'
        )->addColumn(
            'customer_locale',
            \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
            20,
            [],
            'Customer Locale'
        )->addColumn(
            'currency',
            \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
            10,
            [],
            'Currency'
        )->addColumn(
            'device_id',
            \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
            10,
            [],
            'Device Id'
        )->addColumn(
            'cart_details',
            \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
            '64k',
            [],
            'Customer Cart Details'
        )->addColumn(
            'order_id',
            \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
            30,
            [],
            'Order ID'
        )->addColumn(
            'order_placed_on_device',
            \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
            10,
            [],
            'Order Placed Device'
        )->addColumn(
            'applied_coupon_code_on_order',
            \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
            10,
            [],
            'Applied Coupon on order'
        )->addColumn(
            'order_created_on',
            \Magento\Framework\DB\Ddl\Table::TYPE_DATETIME,
            null,
            ['nullable' => true],
            'Order Created Date'
        )->addColumn(
            'created_on',
            \Magento\Framework\DB\Ddl\Table::TYPE_TIMESTAMP,
            null,
            ['nullable' => false, 'default' => Table::TIMESTAMP_INIT],
            'Created On'
        )->addColumn(
            'updated_on',
            Table::TYPE_TIMESTAMP,
            null,
            ['nullable' => false, 'default' => Table::TIMESTAMP_UPDATE],
            'Update On'
        )->setComment(
            'ATG Marketing Table'
        );

        $this->setup->getConnection()->createTable($atgMarketingTable);
    }

    /**
     * @throws \Zend_Db_Exception
     */
    protected function createAtgVpnDataTable()
    {
        $atgVpnDataTable = $this->setup->getConnection()->newTable(
            $this->setup->getTable('atg_vpn_data')
        )->addColumn(
            'entity_id',
            \Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
            null,
            [
                'identity' => true, 'unsigned' => true, 'nullable' => false,
                'primary' => true
            ],
            'Entity Id'
        )->addColumn(
            'vpn',
            \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
            255,
            ['nullable' => false],
            'vpn'
        )->addColumn(
            'name',
            \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
            null,
            [],
            'Product Name'
        )->addColumn(
            'description',
            \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
            null,
            ['nullable' => true],
            'Product description'
        )->addColumn(
            'fabric_contents',
            \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
            null,
            [],
            'Fabric Contents'
        )->addColumn(
            'care_instructions',
            \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
            null,
            [],
            'Instruction'
        )->addColumn(
            'filename',
            \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
            200,
            [],
            'JSON filename'
        )->addColumn(
            'is_factory',
            \Magento\Framework\DB\Ddl\Table::TYPE_BOOLEAN,
            1,
            [],
            'Factory'
        )->addColumn(
            'status',
            \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
            50,
            [],
            'Status'
        )->addColumn(
            'color_codes',
            \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
            null,
            [],
            'Color Codes'
        )->addColumn(
            'created_at',
            \Magento\Framework\DB\Ddl\Table::TYPE_TIMESTAMP,
            null,
            ['nullable' => false, 'default' => Table::TIMESTAMP_INIT],
            'Created Date'
        )->addColumn(
            'updated_at',
            Table::TYPE_TIMESTAMP,
            null,
            ['nullable' => false, 'default' => Table::TIMESTAMP_UPDATE],
            'Update Date'
        )->addIndex(
            $this->setup->getIdxName(
                'atg_vpn_data',
                ['vpn'],
                \Magento\Framework\DB\Adapter\AdapterInterface::INDEX_TYPE_UNIQUE
            ),
            ['vpn'],
            ['type' => \Magento\Framework\DB\Adapter\AdapterInterface::INDEX_TYPE_UNIQUE]
        )->setComment(
            'ATG VPN Data Table'
        );

        $this->setup->getConnection()->createTable($atgVpnDataTable);

    }


    /**
     * @throws \Zend_Db_Exception
     */
    protected function createAtgVpnColorMappingTable()
    {
        $atgVpnColorMappingTable = $this->setup->getConnection()->newTable(
            $this->setup->getTable('atg_vpn_color_mapping')
        )->addColumn(
            'mapping_id',
            \Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
            null,
            [
                'identity' => true,
                'unsigned' => true,
                'nullable' => false,
                'primary' => true
            ],
            'Mapping Id'
        )->addColumn(
            'vpn',
            \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
            255,
            ['nullable' => false, 'unsigned' => true],
            'Vpn'
        )->addColumn(
            'color_code',
            \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
            10,
            ['nullable' => false],
            'Color Code'
        )->addColumn(
            'color_description',
            \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
            null,
            [],
            'Color Description'
        )->addColumn(
            'color_name',
            \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
            null,
            [],
            'Color Name'
        )->addColumn(
            'in_image',
            \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
            null,
            [],
            'IN Image (Z, VLI, AV2_VLI, AV2_Z)'
        )->addColumn(
            'bk_image',
            \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
            null,
            [],
            'BK Image (AV1_Z, AV1_VLI, AV1, AV1_Z)'
        )->addColumn(
            'fr_image',
            \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
            null,
            [],
            'FR Image (AV3_Z, AV3_VLI)'
        )->addColumn(
            'cu_image',
            \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
            null,
            [],
            'CU Image (AV4_Z, AV4_VLI, AV3_VLI)'
        )->addColumn(
            'pk_image',
            \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
            null,
            [],
            'PK Image (AV5_Z, AV5_VLI, AV2_Z, AV2_QL)'
        )->addColumn(
            'sw_image',
            \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
            null,
            [],
            'Swatch Image (S)'
        )->addColumn(
            'poster_url',
            \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
            null,
            [],
            'Poster Image Url (PRST_IMG)'
        )->addColumn(
            'video_url',
            \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
            null,
            [],
            'Video Url AV9_VD'

        )->addIndex(
            $this->setup->getIdxName(
                'atg_vpn_color_mapping',
                ['vpn', 'color_code'],
                \Magento\Framework\DB\Adapter\AdapterInterface::INDEX_TYPE_UNIQUE
            ),
            ['vpn', 'color_code'],
            ['type' => \Magento\Framework\DB\Adapter\AdapterInterface::INDEX_TYPE_UNIQUE]
        )->setComment(
            'ATG VPN Color Mapping Table'
        );

        $this->setup->getConnection()->createTable($atgVpnColorMappingTable);

    }
}
