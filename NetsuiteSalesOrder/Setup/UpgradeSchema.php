<?php

namespace Echidna\NetsuiteSalesOrder\Setup;

use Magento\Framework\Setup\UpgradeSchemaInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use Magento\Framework\Setup\ModuleContextInterface;

class UpgradeSchema implements UpgradeSchemaInterface
{
    /**
     * Upgrades DB schema for a module
     *
     * @param SchemaSetupInterface $setup
     * @param ModuleContextInterface $context
     * @return void
     */
    public function upgrade(SchemaSetupInterface $setup, ModuleContextInterface $context)
    {
        $setup->startSetup();

        $orderTable = 'sales_order';

        //Order table
        $setup->getConnection()
            ->addColumn(
                $setup->getTable($orderTable),
                'netsuit_order_id',
                [
                    'type' => \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                    'length' => 255,
                    'default' => '0',
                    'comment' => 'Netsuite Internal Id'
                ]
            );
		$setup->getConnection()
            ->addColumn(
                $setup->getTable($orderTable),
                'netsuit_fullfillment_sync',
                [
                    'type' => \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                    'length' => 3,
                    'default' => '0',
                    'comment' => 'Netsuite Fulfillment Sync'
                ]
            );
        $setup->endSetup();
    }
}
