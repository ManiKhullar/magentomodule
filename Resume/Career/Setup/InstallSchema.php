<?php
namespace Resume\Career\Setup;

use Magento\Framework\Setup\InstallSchemaInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use Magento\Framework\DB\Ddl\Table;

class InstallSchema implements InstallSchemaInterface
{
    /**
     * Installs DB schema for a module
     *
     * @param SchemaSetupInterface $setup
     * @param ModuleContextInterface $context
     * @return void
     */
    public function install(SchemaSetupInterface $setup, ModuleContextInterface $context)
    {
        $installer = $setup;

        $installer->startSetup();

        if (!$installer->tableExists('resume_career')) {
            $table = $installer->getConnection()
                ->newTable($installer->getTable('resume_career'))
                ->addColumn(
                    'career_id',
                    Table::TYPE_INTEGER,
                    10,
                    ['identity' => true, 'nullable' => false, 'primary' => true, 'unsigned' => true]
                )
                ->addColumn('name', Table::TYPE_TEXT, 200, ['nullable' => false])
                ->addColumn('email', Table::TYPE_TEXT, 150, ['nullable' => false])
                ->addColumn('telephone', Table::TYPE_TEXT, 150, ['nullable' => false])
                ->addColumn('interest_id', Table::TYPE_INTEGER, 15, ['nullable' => false])
                ->addColumn('position_id', Table::TYPE_INTEGER, 15, ['nullable' => false])
                ->addColumn('store_location', Table::TYPE_TEXT, 200, ['nullable' => false])
                ->addColumn('current_location', Table::TYPE_TEXT, 250, ['nullable' => false])
                ->addColumn('resume', Table::TYPE_TEXT, 255, ['nullable' => false])
                ->addColumn('current_ctc', Table::TYPE_TEXT, 150, ['nullable' => false])
                ->addColumn('linkedin_profile', Table::TYPE_TEXT, 150, ['nullable' => false])
                ->addColumn('is_active', Table::TYPE_SMALLINT, null, ['nullable' => false, 'default' => '1'])                
                ->addColumn('creation_time', Table::TYPE_TIMESTAMP, null, ['nullable' => false, 'default'  => Table::TIMESTAMP_INIT], 'Creation Time')
                ->addColumn('update_time', Table::TYPE_TIMESTAMP, null, ['nullable' => false, 'default'  => Table::TIMESTAMP_INIT], 'Update Time')
                ->setComment('career Table');

            $installer->getConnection()->createTable($table);
        }
        
        
        if (!$installer->tableExists('resume_career_interest')) {
            $table = $installer->getConnection()
                ->newTable($installer->getTable('resume_career_interest'))
                ->addColumn(
                    'interest_id',
                    Table::TYPE_INTEGER,
                    10,
                    ['identity' => true, 'nullable' => false, 'primary' => true, 'unsigned' => true]
                )
                ->addColumn('title', Table::TYPE_TEXT, 150, ['nullable' => false])
                ->addColumn('is_active', Table::TYPE_SMALLINT, null, ['nullable' => false, 'default' => '1'])                               
                ->setComment('Career Interest Table');

            $installer->getConnection()->createTable($table);
        }
        
        
        if (!$installer->tableExists('resume_career_position')) {
            $table = $installer->getConnection()
                ->newTable($installer->getTable('resume_career_position'))
                ->addColumn(
                    'position_id',
                    Table::TYPE_INTEGER,
                    10,
                    ['identity' => true, 'nullable' => false, 'primary' => true, 'unsigned' => true]
                )
                ->addColumn('career_interest', Table::TYPE_INTEGER, 15, ['nullable' => false])
                ->addColumn('title', Table::TYPE_TEXT, 150, ['nullable' => false])
                ->addColumn('is_active', Table::TYPE_SMALLINT, null, ['nullable' => false, 'default' => '1'])                                
                ->setComment('Career Position Table');

            $installer->getConnection()->createTable($table);
        }

       

        $installer->endSetup();
    }
}
