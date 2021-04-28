<?php

namespace SM\Performance\Command;

use Magento\Framework\App\State;
use SM\Performance\Helper\CacheKeeper;
use SM\Performance\Model\ProductCacheInstanceFactory;
use SM\Product\Repositories\ProductManagement;
use SM\XRetail\Model\ResourceModel\Outlet\CollectionFactory as OutletCollectionFactory;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class GenerateProductCache extends Command
{
    /**
     * @var CacheKeeper
     */
    protected $cacheKeeper;
    /**
     * @var ProductCacheInstanceFactory
     */
    protected $cacheInstanceFactory;
    /**
     * @var ProductManagement
     */
    protected $productManagement;
    /**
     * @var State
     */
    protected $state;
    /**
     * @var OutletCollectionFactory
     */
    protected $outletCollectionFactory;

    public function __construct(
        CacheKeeper $cacheKeeper,
        ProductCacheInstanceFactory $cacheInstanceFactory,
        ProductManagement $productManagement,
        State $state,
        OutletCollectionFactory $outletCollectionFactory,
        $name = null
    ) {
        parent::__construct($name);
        $this->cacheKeeper = $cacheKeeper;
        $this->cacheInstanceFactory = $cacheInstanceFactory;
        $this->productManagement = $productManagement;
        $this->state = $state;
        $this->outletCollectionFactory = $outletCollectionFactory;
    }

    public function configure()
    {
        $this->setName("cpos:generate-product-cache");
        $this->setDescription("Manually generate product cache tables for ConnectPOS");
        parent::configure();
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        //calculating execution time
        $start = microtime(true);
        try {
            $this->state->getAreaCode();
        } catch (\Magento\Framework\Exception\LocalizedException $e) {
            $this->state->setAreaCode('adminhtml');
        }
        $this->truncateTableCacheInstances();

        $outlets = $this->getOutlets();
        foreach ($outlets as $outlet) {
            $storeId = $outlet->getStoreId();
            $warehouseId = $outlet->getWarehouseId();
            $searchCriteria = new \Magento\Framework\DataObject();
            $searchCriteria->setData('storeId', $storeId);
            $searchCriteria->setData('warehouse_id', $warehouseId);
            $productCollection = $this->productManagement->getProductCollection($searchCriteria);
            $this->processCacheInstance($productCollection, $storeId, $warehouseId);
        }

        $output->writeln('DONE');
        $timeElapsedSecs = microtime(true) - $start;
        $output->writeln('Execution time: ' . $timeElapsedSecs);
    }

    protected function processCacheInstance($productCollection, $storeId, $warehouseId = null)
    {
        $this->cacheKeeper->getInstance($storeId, $warehouseId);
        $cacheInstance = $this->cacheKeeper->getInstance($storeId, $warehouseId);
        $cacheInstance->getResource()->setStoreId($storeId);
        $cacheInstance->getResource()->setWarehouseId($warehouseId);
        $cacheInfo = $this->cacheKeeper->getCacheInstanceInfo($storeId, $warehouseId);

        foreach ($productCollection as $product) {
            $xProduct =  $this->productManagement->processXProduct(
                $product,
                $storeId,
                $warehouseId
            );

            $cacheInstance->setData('id', $xProduct->getId())
                ->setData('data', json_encode($xProduct->getData()))
                ->save();

        }

        $cacheInfo->setData('cache_time', CacheKeeper::getCacheTime());
        $cacheInfo->setData('page_size', 50);
        $cacheInfo->setData('current_page', (int) $productCollection->getSize() / 50 + 1);
        $cacheInfo->setData('is_over', true);
        $cacheInfo->save();

        return $this;
    }

    protected function truncateTableCacheInstances()
    {
        $cacheModel = $this->cacheInstanceFactory->create();
        /** @var \Magento\Framework\DB\Adapter\AdapterInterface $connection */
        $connection = $cacheModel->getResource()->getConnection();
        $tableName = $cacheModel->getResource()->getMainTable();
        $connection->truncateTable($tableName);
        return $this;
    }

    protected function getOutlets()
    {
        $collection = $this->outletCollectionFactory->create();
        $collection->addFieldToSelect('store_id')
            ->addFieldToSelect('warehouse_id');
        $collection->getSelect()->group(['main_table.store_id', 'main_table.warehouse_id']);
        return $collection;
    }
}
