<?php
/**
 * Created by mr.vjcspy@gmail.com - khoild@smartosc.com.
 * Date: 08/03/2017
 * Time: 11:19
 */

namespace SM\Performance\Observer;

use Magento\Catalog\Model\Category;
use Magento\Catalog\Model\Product;
use Magento\Customer\Model\Customer;
use Magento\Customer\Model\Group;
use Magento\Framework\Cache\FrontendInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Store\Model\StoreManagerInterface;
use SM\Performance\Helper\CacheKeeper;
use SM\Performance\Helper\RealtimeManager;
use SM\Performance\Model\Cache\Type\RetailProduct;

/**
 * Class ModelAfterDelete
 *
 * @package SM\Performance\Observer
 */
class ModelAfterDelete implements ObserverInterface
{
    /**
     * @var \SM\Performance\Helper\RealtimeManager
     */
    private $realtimeManager;
    /**
     * @var \Magento\Framework\Cache\FrontendInterface
     */
    private $cache;
    /**
     * @var \SM\Performance\Observer\StoreManagerInterface
     */
    private $storeManager;
    /**
     * @var \SM\Performance\Helper\CacheKeeper
     */
    private $cacheKeeper;

    /**
     * ModelAfterDelete constructor.
     *
     * @param \SM\Performance\Helper\RealtimeManager     $realtimeManager
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     * @param \Magento\Framework\Cache\FrontendInterface $cache
     * @param \SM\Performance\Helper\CacheKeeper         $cacheKeeper
     */
    public function __construct(
        RealtimeManager $realtimeManager,
        StoreManagerInterface $storeManager,
        FrontendInterface $cache,
        CacheKeeper $cacheKeeper
    ) {
        $this->cacheKeeper     = $cacheKeeper;
        $this->storeManager    = $storeManager;
        $this->cache           = $cache;
        $this->realtimeManager = $realtimeManager;
    }

    /**
     * @param \Magento\Framework\Event\Observer $observer
     *
     * @throws \Exception
     */
    public function execute(Observer $observer)
    {
        $object = $observer->getData('object');

        if ($object instanceof Customer) {
            $this->realtimeManager->trigger(
                RealtimeManager::CUSTOMER_ENTITY,
                $object->getId(),
                RealtimeManager::TYPE_CHANGE_REMOVE
            );
        }

        if ($object instanceof Category) {
            $this->realtimeManager->trigger(
                RealtimeManager::CATEGORY_ENTITY,
                $object->getId(),
                RealtimeManager::TYPE_CHANGE_REMOVE
            );
        }

        if ($object instanceof Group) {
            $this->realtimeManager->trigger(
                RealtimeManager::CUSTOMER_GROUP,
                $object->getData('customer_group_id'),
                RealtimeManager::TYPE_CHANGE_REMOVE
            );
        }

        if ($object instanceof Product) {
            $this->cacheKeeper->deleteEntity($object->getId());
            $this->realtimeManager->trigger(
                RealtimeManager::PRODUCT_ENTITY,
                $object->getId(),
                RealtimeManager::TYPE_CHANGE_REMOVE
            );
        }
    }
}
