<?php
/**
 * Created by KhoiLe - mr.vjcspy@gmail.com
 * Date: 5/16/17
 * Time: 3:19 PM
 */

namespace SM\Performance\Observer\ProductCaching;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\ObjectManagerInterface;
use SM\Integrate\Model\WarehouseIntegrateManagement;
use SM\Performance\Helper\CacheKeeper;

class BeforeLoading implements ObserverInterface
{
    /**
     * @var \SM\Performance\Helper\CacheKeeper
     */
    protected $cacheKeeper;
    /**
     * @var \Magento\Framework\ObjectManagerInterface
     */
    private $objectManager;

    /**
     * @var \SM\Core\Api\Data\XProductFactory
     */
    protected $xProductFactory;

    /**
     * @var RequestInterface
     */
    private $request;

    /**
     * BeforeLoading constructor.
     *
     * @param \SM\Performance\Helper\CacheKeeper        $cacheKeeper
     * @param \Magento\Framework\ObjectManagerInterface $objectManager
     * @param \SM\Core\Api\Data\XProductFactory         $xProductFactory
     * @param RequestInterface                          $request
     */
    public function __construct(
        CacheKeeper $cacheKeeper,
        ObjectManagerInterface $objectManager,
        \SM\Core\Api\Data\XProductFactory $xProductFactory,
        RequestInterface $request
    ) {
        $this->cacheKeeper = $cacheKeeper;
        $this->objectManager = $objectManager;
        $this->xProductFactory = $xProductFactory;
        $this->request = $request;
    }

    /**
     * @param \Magento\Framework\Event\Observer $observer
     *
     * @throws \Exception
     */
    public function execute(Observer $observer)
    {
        $loadingData = $observer->getData('loading_data');
        /** @var \Magento\Framework\DataObject $searchCriteria */
        $searchCriteria = $loadingData->getData('search_criteria');
        $storeId = $searchCriteria->getData('storeId');
        $warehouseId = WarehouseIntegrateManagement::getWarehouseId();
        $outletId = WarehouseIntegrateManagement::getOutletId();

        if (!$warehouseId) {
            if (isset($searchCriteria['warehouse_id']) && $searchCriteria['warehouse_id']) {
                $warehouseId = $searchCriteria['warehouse_id'];
            } else {
                $warehouseId = $this->request->getParam('warehouse_id');
            }
            WarehouseIntegrateManagement::setWarehouseId($warehouseId);
        }

        if (!$outletId) {
            if (isset($searchCriteria['outlet_id']) && $searchCriteria['outlet_id']) {
                $outletId = $searchCriteria['outlet_id'];
            } else {
                $outletId = $this->request->getParam('outlet_id');
            }
            WarehouseIntegrateManagement::setOutletId($outletId);
        }

        $cacheInfo = $this->cacheKeeper->getCacheInstanceInfo($storeId, $warehouseId);

        $currentPage = $searchCriteria->getData('currentPage');
        $pageSize = $searchCriteria->getData('pageSize');

        if (!$cacheInfo || !CacheKeeper::$USE_CACHE) {
            return;
        }

        $isRealTime = floatval($searchCriteria->getData('realTime')) == 1;
        $cacheTime = $searchCriteria->getData('cache_time');

        if ($isRealTime) {
            $loadingData->setData(CacheKeeper::$IS_REALTIME, true);
            if (!$cacheTime || is_nan((float)$cacheTime)) {
                throw new \Exception("Realtime must have param cache_time and cache time must be number");
            }

            if ($searchCriteria->getData('forceRealTime')) {
                return;
            }

            if (floatval($cacheInfo->getData('cache_time')) < floatval($cacheTime)
                || boolval($cacheInfo->getData('is_over')) !== true
            ) {
                return;
            }

            /** @var \SM\Performance\Model\AbstractProductCache $cacheInstance */
            $cacheInstance = $this->cacheKeeper->getInstance($storeId, $warehouseId);
            $collection = $cacheInstance->getCollection();

            if ($searchCriteria->getData('entity_id') || $searchCriteria->getData('entityId')) {
                if (is_null($searchCriteria->getData('entity_id'))) {
                    $ids = $searchCriteria->getData('entityId');
                } else {
                    $ids = $searchCriteria->getData('entity_id');
                }
                $collection->addFieldToFilter('id', ['in' => explode(",", (string)$ids)]);
            }
            $loadingData->setData(CacheKeeper::$IS_PULL_FROM_CACHE, true);
            $loadingData->setData('collection', $collection);
            $loadingData->setData('items', $this->retrieveDataFromCollection($collection));
        } elseif (($cacheInfo && boolval($cacheInfo->getData('is_over')) === true)
            || ($currentPage <= $cacheInfo->getData('current_page')
                && intval($pageSize) === intval($cacheInfo->getData('page_size'))
                && $searchCriteria->getData('productIds') === null
                && $searchCriteria->getData('entityId') === null
                && $searchCriteria->getData('entity_id') === null)
        ) {
            /** @var \SM\Performance\Model\AbstractProductCache $cacheInstance */
            $cacheInstance = $this->cacheKeeper->getInstance($storeId, $warehouseId);

            if (!$cacheInstance) {
                throw new \Exception("Error SM\\Performance\\Observer\\ProductCaching\\BeforeLoading");
            }

            $collection = $cacheInstance->getCollection();
            $collection->setCurPage($currentPage);
            if ($searchCriteria->getData('productIds')) {
                $collection->addFieldToFilter('id', ['in' => $searchCriteria->getData('productIds')]);
            }
            if ($searchCriteria->getData('entity_id') || $searchCriteria->getData('entityId')) {
                if (is_null($searchCriteria->getData('entity_id'))) {
                    $ids = $searchCriteria->getData('entityId');
                } else {
                    $ids = $searchCriteria->getData('entity_id');
                }
                $collection->addFieldToFilter('id', ['in' => explode(",", (string)$ids)]);
            }

            if ($cacheInfo && boolval($cacheInfo->getData('is_over')) === true) {
                $collection->setPageSize(500);
            } else {
                $collection->setPageSize($pageSize);
            }

            $loadingData->setData(CacheKeeper::$IS_PULL_FROM_CACHE, true);
            $loadingData->setData('collection', $collection);

            if ($collection->getLastPageNumber() < $currentPage) {
                $loadingData->setData('items', []);
            } else {
                $loadingData->setData('items', $this->retrieveDataFromCollection($collection));
            }

            $loadingData->setData('cache_time', $cacheInfo->getData('cache_time'));
        }
    }

    /**
     * @param $collection
     *
     * @return array
     */
    protected function retrieveDataFromCollection($collection)
    {
        $items = [];
        foreach ($collection as $item) {
            $itemData = json_decode((string)$item->getData('data'), true);
            if (is_array($itemData)) {
                $xProduct = $this->xProductFactory->create();
                $xProduct->setData($itemData);
                $items[] = $xProduct;
            }
        }

        return $items;
    }
}
