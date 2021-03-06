<?php
/**
 * Created by KhoiLe - mr.vjcspy@gmail.com
 * Date: 5/16/17
 * Time: 3:19 PM
 */

namespace SM\Performance\Observer\ProductCaching;

use Exception;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\ObjectManagerInterface;
use SM\Integrate\Model\WarehouseIntegrateManagement;
use SM\Performance\Helper\CacheKeeper;

/**
 * Class AfterLoading
 *
 * @package SM\Performance\Observer\ProductCaching
 */
class AfterLoading implements ObserverInterface
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
     * @var RequestInterface
     */
    private $request;

    /**
     * AfterLoading constructor.
     *
     * @param \SM\Performance\Helper\CacheKeeper        $cacheKeeper
     * @param \Magento\Framework\ObjectManagerInterface $objectManager
     * @param RequestInterface                          $request
     */
    public function __construct(
        CacheKeeper $cacheKeeper,
        ObjectManagerInterface $objectManager,
        RequestInterface $request
    ) {
        $this->cacheKeeper = $cacheKeeper;
        $this->objectManager = $objectManager;
        $this->request = $request;
    }

    /**
     * @param Observer $observer
     *
     * @return void
     * @throws \Exception
     */
    public function execute(Observer $observer)
    {
        // TODO: Implement execute() method.
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

        $this->cacheKeeper->getInstance($storeId, $warehouseId);
        $cacheInfo = $this->cacheKeeper->getCacheInstanceInfo($storeId, $warehouseId);

        if ($loadingData->getData(CacheKeeper::$IS_PULL_FROM_CACHE) !== true) {
            /** @var \SM\Core\Api\Data\XProduct[] $items */
            $items = $loadingData->getData('items');

            foreach ($items as $item) {
                $cacheInstance = $this->cacheKeeper->getInstance($storeId, $warehouseId);
                try {
                    $cacheInstance->setData('id', $item->getId())
                        ->setData('data', json_encode($item->getData()))
                        ->save();
                } catch (Exception $e) {
                }
            }
        }

        $cacheInfo->setData('cache_time', CacheKeeper::getCacheTime());
        $cacheInfo->setData('page_size', $searchCriteria->getData('pageSize'));
        $cacheInfo->setData('current_page', $searchCriteria->getData('currentPage'));

        if ($loadingData->getData('is_full_loading') === true) {
            $cacheInfo->setData('is_over', true);
        }

        $cacheInfo->save();
    }
}
