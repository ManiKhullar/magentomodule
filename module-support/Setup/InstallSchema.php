<?php
namespace Altayer\Support\Setup;

use \Magento\Framework\Setup\InstallSchemaInterface;
use \Magento\Framework\Setup\SchemaSetupInterface;
use \Magento\Framework\Setup\ModuleContextInterface;

/**
 * Class InstallSchema
 * @package Altayer\Support\Setup
 */
class InstallSchema implements InstallSchemaInterface
{
    public function install(SchemaSetupInterface $setup, ModuleContextInterface $context)
    {
        $installer = $setup;
        $installer->startSetup();

        $table = $installer->getConnection()->newTable(
            $installer->getTable('atg_report')
        )->addColumn(
            'report_id',
            \Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
            null,
            ['identity' => true, 'nullable' => false, 'primary' => true, 'unsigned' => true,],
            'Entity ID'
        )->addColumn(
            'report_name',
            \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
            255,
            ['nullable' => false,],
            'Report Name'
        )->addColumn(
            'report_created_by',
            \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
            255,
            ['nullable' => false,],
            'Report Created By User'
        )->addColumn(
            'report_modified_by',
            \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
            255,
            ['nullable' => false,],
            'Report Modified By User'
        )->addColumn(
            'report_sql',
            \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
            10000,
            ['nullable' => false,],
            'Report Name'
        )->addColumn(
            'created_at',
            \Magento\Framework\DB\Ddl\Table::TYPE_TIMESTAMP,
            null,
            ['nullable' => false, 'default' => \Magento\Framework\DB\Ddl\Table::TIMESTAMP_INIT,],
            'Creation Time'
        )->addColumn(
            'updated_at',
            \Magento\Framework\DB\Ddl\Table::TYPE_TIMESTAMP,
            null,
            ['nullable' => false, 'default' => \Magento\Framework\DB\Ddl\Table::TIMESTAMP_INIT_UPDATE,],
            'Modification Time'
        );
        $installer->getConnection()->createTable($table);
        $installer->endSetup();
    }
}
