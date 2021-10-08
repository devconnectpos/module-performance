<?php
/**
 * Copyright © 2016 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace SM\Performance\Setup;

use Magento\Framework\Setup\UpgradeSchemaInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Ddl\Table;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @codeCoverageIgnore
 */
class UpgradeSchema implements UpgradeSchemaInterface {

    /**
     * {@inheritdoc}
     */
    public function upgrade(SchemaSetupInterface $setup, ModuleContextInterface $context)
    {
        if (version_compare($context->getVersion(), '0.0.9', '<')) {
            $this->addProductCacheInstanceTable($setup);
        }

        if (version_compare($context->getVersion(), '0.0.8', '<')) {
            $this->addRealtimeStorageTable($setup);
        }
    }

    /**
     * @param SchemaSetupInterface $setup
     * @param OutputInterface      $output
     *
     * @throws \Zend_Db_Exception
     */
    public function execute(SchemaSetupInterface $setup, OutputInterface $output)
    {
        $output->writeln('  |__ Create realtime storage table');
        $this->addRealtimeStorageTable($setup);
        $output->writeln('  |__ Create product cache instance table');
        $this->addProductCacheInstanceTable($setup);
    }

    /**
     * @param \Magento\Framework\Setup\SchemaSetupInterface   $setup
     * @param \Magento\Framework\Setup\ModuleContextInterface $context
     *
     * @throws \Zend_Db_Exception
     */
    protected function addRealtimeStorageTable(SchemaSetupInterface $setup)
    {
        $setup->startSetup();

        if ($setup->getConnection()->isTableExists($setup->getTable('sm_realtime_storage'))) {
            $setup->endSetup();

            return;
        }

        $table = $setup->getConnection()->newTable(
            $setup->getTable('sm_realtime_storage')
        )->addColumn(
            'id',
            Table::TYPE_INTEGER,
            null,
            ['identity' => true, 'nullable' => false, 'primary' => true, 'unsigned' => true,],
            'Entity ID'
        )->addColumn(
            'data_realtime',
            Table::TYPE_TEXT,
            null,
            ['nullable' => true, 'unsigned' => true,],
            'Data Realtime'
        )->addColumn(
            'creation_time',
            Table::TYPE_TIMESTAMP,
            null,
            ['nullable' => false, 'default' => Table::TIMESTAMP_INIT],
            'Creation Time'
        );
        $setup->getConnection()->createTable($table);
        $setup->endSetup();
    }

    /**
     * @param \Magento\Framework\Setup\SchemaSetupInterface   $setup
     * @param \Magento\Framework\Setup\ModuleContextInterface $context
     *
     * @throws \Zend_Db_Exception
     */
    protected function addProductCacheInstanceTable(SchemaSetupInterface $setup)
    {
        $setup->startSetup();

        if ($setup->getConnection()->isTableExists($setup->getTable('sm_performance_product_cache_instance'))) {
            $setup->endSetup();

            return;
        }

        $table = $setup->getConnection()->newTable(
            $setup->getTable('sm_performance_product_cache_instance')
        )->addColumn(
            'id',
            Table::TYPE_INTEGER,
            null,
            ['identity' => true, 'nullable' => false, 'primary' => true, 'unsigned' => true,],
            'Entity ID'
        )->addColumn(
            'is_over',
            Table::TYPE_SMALLINT,
            null,
            ['nullable' => true, 'unsigned' => true,],
            'Has Full Cache'
        )->addColumn(
            'cache_time',
            Table::TYPE_TEXT,
            null,
            ['nullable' => true, 'unsigned' => true,],
            'Cache Time'
        )->addColumn(
            'warehouse_id',
            Table::TYPE_TEXT,
            255000,
            ['nullable' => false],
            'WareHouse ID'
        )->addColumn(
            'store_id',
            Table::TYPE_INTEGER,
            null,
            ['nullable' => true, 'unsigned' => true,],
            'Store ID'
        )->addColumn(
            'page_size',
            Table::TYPE_INTEGER,
            null,
            ['nullable' => true, 'unsigned' => true,],
            'Store ID'
        )->addColumn(
            'current_page',
            Table::TYPE_INTEGER,
            null,
            ['nullable' => true, 'unsigned' => true,],
            'Store ID'
        )->addColumn(
            'creation_time',
            Table::TYPE_TIMESTAMP,
            null,
            ['nullable' => false, 'default' => Table::TIMESTAMP_INIT],
            'Creation Time'
        )->addColumn(
            'update_time',
            Table::TYPE_TIMESTAMP,
            null,
            ['nullable' => false, 'default' => Table::TIMESTAMP_INIT_UPDATE],
            'Modification Time'
        );
        $setup->getConnection()->createTable($table);
        $setup->endSetup();
    }
}
