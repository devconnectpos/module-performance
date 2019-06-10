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
/**
 * @codeCoverageIgnore
 */
class UpgradeSchema implements UpgradeSchemaInterface {

    /**
     * {@inheritdoc}
     */
    public function upgrade(SchemaSetupInterface $setup, ModuleContextInterface $context)
    {
        $installer = $setup;
        $installer->startSetup();
        if (version_compare($context->getVersion(), '0.0.9', '<')) {
            $this->addProductCacheInstanceTable($setup);
        }

        if (version_compare($context->getVersion(), '0.0.8', '<')) {
            $this->addRealtimeStorageTable($setup);
        }

        if (version_compare($context->getVersion(), '0.1.0', '<')) {
            $this->modifyColumnWarehouseProductCacheInstanceTable($setup);
        }
    }

    /**
     * @param \Magento\Framework\Setup\SchemaSetupInterface   $setup
     * @param \Magento\Framework\Setup\ModuleContextInterface $context
     */
    protected function addRealtimeStorageTable(SchemaSetupInterface $setup)
    {
        $installer = $setup;
        $installer->startSetup();
        $setup->getConnection()->dropTable($setup->getTable('sm_realtime_storage'));
        $table = $installer->getConnection()->newTable(
            $installer->getTable('sm_realtime_storage')
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
        $installer->getConnection()->createTable($table);

        $installer->endSetup();
    }

    /**
     * @param \Magento\Framework\Setup\SchemaSetupInterface   $setup
     * @param \Magento\Framework\Setup\ModuleContextInterface $context
     */
    protected function addProductCacheInstanceTable(SchemaSetupInterface $setup)
    {
        $installer = $setup;
        $installer->startSetup();
        $setup->getConnection()->dropTable($setup->getTable('sm_performance_product_cache_instance'));
        $table = $installer->getConnection()->newTable(
            $installer->getTable('sm_performance_product_cache_instance')
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
            Table::TYPE_INTEGER,
            null,
            ['nullable' => true, 'unsigned' => true,],
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
        $installer->getConnection()->createTable($table);

        $installer->endSetup();
    }

    protected function modifyColumnWarehouseProductCacheInstanceTable(SchemaSetupInterface $setup)
    {
        $productCacheInstanceTable = $setup->getTable('sm_performance_product_cache_instance');
        $setup->startSetup();

        $setup->getConnection()->changeColumn(
            $setup->getTable($productCacheInstanceTable),
            'warehouse_id',
            'warehouse_id',
            [
                'type' => Table::TYPE_TEXT,
                'length' => 255000,
                ['nullable' => false],
                'Warehouse ID'
            ]
        );

        $setup->endSetup();
    }
}
