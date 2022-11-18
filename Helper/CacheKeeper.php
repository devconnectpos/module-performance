<?php
/**
 * Created by KhoiLe - mr.vjcspy@gmail.com
 * Date: 5/15/17
 * Time: 3:51 PM
 */

namespace SM\Performance\Helper;

use Exception;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Ddl\Table;
use Magento\Store\Model\StoreManagerInterface;
use SM\Integrate\Model\WarehouseIntegrateManagement;
use SM\Performance\Model\IzProductFactory;
use SM\Performance\Model\ProductCacheInstanceFactory;
use SM\Performance\Model\ResourceModel\ProductCacheInstance\CollectionFactory;
use SM\Product\Repositories\ProductManagement;
use SM\XRetail\Model\ResourceModel\Outlet\CollectionFactory as OutletCollectionFactory;

/**
 * Class CacheKeeper
 *
 * @package SM\Performance\Helper
 */
class CacheKeeper
{

    /**
     * @var bool
     */
    public static $USE_CACHE = true;
    public static $IS_PULL_FROM_CACHE = 'IS_PULL_FROM_CACHE';
    public static $IS_REALTIME = false;

    /**
     * @var ProductManagement
     */
    protected $productManagement;
    /**
     * @var OutletCollectionFactory
     */
    protected $outletCollectionFactory;
    /**
     * @var array
     */
    private $cachedInstance = [];
    /**
     * @var \SM\Performance\Model\ResourceModel\ProductCacheInstance\CollectionFactory
     */
    protected $productCacheInstanceCollectionFactory;
    /**
     * @var \SM\Performance\Model\ProductCacheInstanceFactory
     */
    private $productCacheInstanceFactory;
    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    private $storeManager;
    /**
     * @var \SM\Performance\Model\IzProductFactory
     */
    private $izProductFactory;
    /**
     * @var \Magento\Framework\Setup\SchemaSetupInterface
     */
    private $schemaSetup;
    /**
     * @var \Magento\Framework\DB\Adapter\AdapterInterface
     */
    private $connection;
    /**
     * @var \Magento\Framework\App\ResourceConnection
     */
    private $resource;

    /**
     * CacheKeeper constructor.
     *
     * @param \SM\Performance\Model\ResourceModel\ProductCacheInstance\CollectionFactory $productCacheInstanceCollectionFactory
     * @param \SM\Performance\Model\ProductCacheInstanceFactory                          $productCacheInstanceFactory
     * @param \Magento\Store\Model\StoreManagerInterface                                 $storeManager
     * @param \SM\Performance\Model\IzProductFactory                                     $izProductFactory
     * @param \Magento\Framework\App\ResourceConnection                                  $resource
     * @param ProductManagement                                                          $productManagement
     * @param OutletCollectionFactory                                                    $outletCollectionFactory
     */
    public function __construct(
        CollectionFactory $productCacheInstanceCollectionFactory,
        ProductCacheInstanceFactory $productCacheInstanceFactory,
        StoreManagerInterface $storeManager,
        IzProductFactory $izProductFactory,
        ResourceConnection $resource,
        ProductManagement $productManagement,
        OutletCollectionFactory $outletCollectionFactory
    ) {
        $this->productCacheInstanceCollectionFactory = $productCacheInstanceCollectionFactory;
        $this->productCacheInstanceFactory = $productCacheInstanceFactory;
        $this->storeManager = $storeManager;
        $this->izProductFactory = $izProductFactory;
        $this->resource = $resource;
        $this->connection = $resource->getConnection();
        $this->productManagement = $productManagement;
        $this->outletCollectionFactory = $outletCollectionFactory;
    }

    /**
     * @param      $storeId
     * @param null $warehouseId
     *
     * @return \SM\Performance\Model\AbstractProductCache
     * @throws \Exception
     */
    public function getInstance($storeId, $warehouseId = null)
    {
        if ($this->storeManager->getStore()->getId() != $storeId) {
            $this->storeManager->setCurrentStore($storeId);
        }
        $instanceInfo = $this->getCacheInstanceInfo($storeId, $warehouseId);
        if ($instanceInfo === null) {
            $this->buildTable($storeId, $warehouseId);
            $_m = $this->getProductCacheInstanceModel();
            $_m->setData('is_over', 0)
                ->setData('store_id', $storeId)
                ->save();
            if (!empty($warehouseId)) {
                $_m->setData('warehouse_id', $warehouseId)
                    ->save();
            }
        }

        return $this->getIzProductModel();
    }

    /**
     * @return \SM\Performance\Model\IzProduct
     */
    protected function getIzProductModel()
    {
        return $this->izProductFactory->create();
    }

    /**
     * @param $storeId
     * @param $warehouseId
     *
     * @return \SM\Performance\Model\ProductCacheInstance|null
     * @throws \Exception
     */
    public function getCacheInstanceInfo($storeId, $warehouseId = null)
    {
        $cacheKey = $this->getCacheKey([$storeId, $warehouseId]);
        if (!isset($this->cachedInstance[$cacheKey])) {
            $collection = $this->getProductCacheInstanceCollection();

            $collection->addFieldToFilter('store_id', $storeId);

            if (!empty($warehouseId)) {
                $collection->addFieldToFilter('warehouse_id', $warehouseId);
            }

            $instanceInfo = $collection->getFirstItem();

            if ($instanceInfo->getId()) {
                if (!$this->connection->isTableExists($this->getTableName($storeId, $warehouseId))) {
                    //throw new Exception("Cannot find product cache table in the database. Please navigate to Setting > Advanced > Magento Cache and delete all caches then try again.");
                    return $this->cachedInstance[$cacheKey];
                }
                $this->cachedInstance[$cacheKey] = $instanceInfo;
            } else {
                $this->cachedInstance[$cacheKey] = null;
            }
        }

        return $this->cachedInstance[$cacheKey];
    }

    /**
     * @return \SM\Performance\Model\ResourceModel\ProductCacheInstance\Collection
     */
    protected function getProductCacheInstanceCollection()
    {
        return $this->productCacheInstanceCollectionFactory->create();
    }

    /**
     * @return \SM\Performance\Model\ProductCacheInstance
     */
    protected function getProductCacheInstanceModel()
    {
        return $this->productCacheInstanceFactory->create();
    }

    /**
     * @param $arrayKey
     *
     * @return mixed
     */
    public function getCacheKey($arrayKey)
    {
        return array_reduce(
            $arrayKey,
            function ($carry, $item) {
                $carry .= $item."|";

                return $carry;
            },
            ""
        );
    }

    /**
     * @return int
     */
    public static function getCacheTime()
    {
        return intval(microtime(true) * 1000);
    }

    /**
     * @param      $storeId
     * @param null $warehouseId
     *
     * @throws \Zend_Db_Exception
     */
    protected function buildTable($storeId, $warehouseId = null)
    {
        $table = $this->connection->newTable($this->getTableName($storeId, $warehouseId));
        $table
            ->addColumn(
                'id',
                Table::TYPE_INTEGER,
                null,
                ['identity' => true, 'nullable' => false, 'primary' => true, 'unsigned' => true,],
                'Product id'
            )
            ->addColumn(
                'data',
                Table::TYPE_TEXT,
                Table::MAX_TEXT_SIZE,
                ['nullable' => true, 'unsigned' => true,],
                'Product Data'
            )
            ->addColumn(
                'creation_time',
                Table::TYPE_TIMESTAMP,
                null,
                ['nullable' => false, 'default' => Table::TIMESTAMP_INIT],
                'Creation Time'
            )
            ->addColumn(
                'update_time',
                Table::TYPE_TIMESTAMP,
                null,
                ['nullable' => false, 'default' => Table::TIMESTAMP_INIT_UPDATE],
                'Modification Time'
            );
        $table->addIndex(
            $this->resource->getIdxName($this->getTableName($storeId, $warehouseId), ['id']),
            ['id']
        );
        $this->dropCacheTable($storeId, $warehouseId);
        $this->connection->createTable($table);
    }

    public function dropCacheTable($storeId, $warehouseId)
    {
        $this->connection->dropTable($this->getTableName($storeId, $warehouseId));
    }

    /**
     * @param      $storeId
     * @param null $warehouseId
     *
     * @return string
     */
    protected function getTableName($storeId, $warehouseId = null)
    {
        return $this->resource->getTableName('iz_product_'.$storeId.'_'.$warehouseId);
    }

    public function deleteEntity($ids)
    {
        $collection = $this->getProductCacheInstanceCollection();
        foreach ($collection as $cacheInstanceInfo) {
            WarehouseIntegrateManagement::setWarehouseId($cacheInstanceInfo->getData('warehouse_id'));
            WarehouseIntegrateManagement::setOutletId($cacheInstanceInfo->getData('outlet_id'));
            try {
                $this->getIzProductModel()
                    ->getCollection()
                    ->addFieldToFilter('id', ['in' => explode(",", (string)$ids)])
                    ->walk('delete');
            } catch (Exception $e) {
            }
        }
    }

    public function generateProductCacheTables()
    {
        $this->truncateTableCacheInstances();

        $outlets = $this->getOutlets();
        foreach ($outlets as $outlet) {
            $storeId = $outlet->getStoreId();
            try {
                $this->storeManager->getStore($storeId);
            } catch (\Magento\Framework\Exception\NoSuchEntityException $exception) {
                continue;
            }
            $warehouseId = $outlet->getWarehouseId();
            $searchCriteria = new \Magento\Framework\DataObject();
            $searchCriteria->setData('storeId', $storeId);
            $searchCriteria->setData('warehouse_id', $warehouseId);
            $productCollection = $this->productManagement->getProductCollection($searchCriteria);
            $this->processCacheInstance($productCollection, $storeId, $warehouseId);
        }

        return $this;
    }

    protected function processCacheInstance($productCollection, $storeId, $warehouseId = null)
    {
        $this->getInstance($storeId, $warehouseId);
        $cacheInstance = $this->getInstance($storeId, $warehouseId);
        $cacheInstance->getResource()->setStoreId($storeId);
        $cacheInstance->getResource()->setWarehouseId($warehouseId);
        $cacheInfo = $this->getCacheInstanceInfo($storeId, $warehouseId);

        foreach ($productCollection as $product) {
            $xProduct = $this->productManagement->processXProduct(
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
        $cacheInfo->setData('current_page', (int)$productCollection->getSize() / 50 + 1);
        $cacheInfo->setData('is_over', true);
        $cacheInfo->save();

        return $this;
    }

    protected function truncateTableCacheInstances()
    {
        $cacheModel = $this->productCacheInstanceFactory->create();
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
            ->addFieldToSelect('warehouse_id')
            ->addFieldToFilter('is_active', ['eq' => 1]);
        $collection->getSelect()->group(['main_table.store_id', 'main_table.warehouse_id']);

        return $collection;
    }
}
