<?php

namespace SM\Performance\Observer;

use Magento\Catalog\Model\Category;
use Magento\Catalog\Model\Product;
use Magento\Customer\Model\Customer;
use Magento\Customer\Model\Group;
use Magento\Framework\Cache\FrontendInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\ObjectManagerInterface;
use Magento\Quote\Model\Quote\Item;
use Magento\Store\Model\StoreManagerInterface;
use SM\Performance\Helper\RealtimeManager;
use SM\Sales\Repositories\OrderManagement;
use SM\XRetail\Helper\Data;

/**
 * Class ModelAfterSave
 *
 * @package SM\Performance\Observer
 */
class ModelAfterSave implements ObserverInterface
{
    /**
     * @var \SM\Performance\Helper\RealtimeManager
     */
    private $realtimeManager;
    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    private $storeManager;
    /**
     * @var \Magento\Framework\Cache\FrontendInterface
     */
    private $cache;
    /**
     * @var \SM\XRetail\Helper\Data
     */
    private $retailHepler;
    /**
     * @var \Magento\Framework\ObjectManagerInterface
     */
    private $objectManager;

    /**
     * @var array
     */
    private $fieldOriginalProductCheck
        = [
            "name",
            "sku",
            "image",
            "special_price",
            "price"
        ];

    public static $SUPPORT_CHECK_REALTIME_API = false;

    /**
     * ModelAfterSave constructor.
     *
     * @param \SM\Performance\Helper\RealtimeManager     $realtimeManager
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     * @param \Magento\Framework\Cache\FrontendInterface $cache
     * @param \SM\XRetail\Helper\Data                    $helperData
     * @param \Magento\Framework\ObjectManagerInterface  $objectManager
     */
    public function __construct(
        RealtimeManager $realtimeManager,
        StoreManagerInterface $storeManager,
        FrontendInterface $cache,
        Data $helperData,
        ObjectManagerInterface $objectManager
    ) {
        $this->objectManager   = $objectManager;
        $this->storeManager    = $storeManager;
        $this->realtimeManager = $realtimeManager;
        $this->cache           = $cache;
        $this->retailHepler    = $helperData;
    }

    /**
     * @param \Magento\Framework\Event\Observer $observer
     *
     * @throws \Exception
     */
    public function execute(Observer $observer)
    {
        $object = $observer->getData('object');

        $request = $this->objectManager->get("Magento\Framework\App\RequestInterface");

        if ($object instanceof Customer) {
            $this->realtimeManager->trigger(
                RealtimeManager::CUSTOMER_ENTITY,
                $object->getId(),
                RealtimeManager::TYPE_CHANGE_UPDATE
            );
        }

        if ($object instanceof Category) {
            $this->realtimeManager->trigger(
                RealtimeManager::CATEGORY_ENTITY,
                $object->getId(),
                RealtimeManager::TYPE_CHANGE_UPDATE
            );
        }

        // move category
        if ($observer->getData('category') instanceof Category && $observer->getData('category_id')) {
            $this->realtimeManager->trigger(
                RealtimeManager::CATEGORY_ENTITY,
                $observer->getData('category_id'),
                RealtimeManager::TYPE_CHANGE_UPDATE
            );
        }

        if ($object instanceof Group) {
            $this->realtimeManager->trigger(
                RealtimeManager::CUSTOMER_GROUP,
                $object->getData('customer_group_id'),
                RealtimeManager::TYPE_CHANGE_UPDATE
            );
        }

        if ($object instanceof Product) {
            if (ModelAfterSave::$SUPPORT_CHECK_REALTIME_API) {
                if ($request && false !== strpos($request->getPathInfo(), "rest/V1/products")) {
                    if (!$this->isDataChange($object)) {
                        return;
                    }
                }
            }

            $ids = [];
            array_push($ids, $object->getId());
            if ($object->getTypeId() == 'configurable') {
                /** @var \Magento\ConfigurableProduct\Model\Product\Type\Configurable $instanceType */
                $instanceType = $object->getTypeInstance();
                $childIds     = $instanceType->getChildrenIds($object->getId());
                foreach ($childIds as $_ids) {
                    $ids = array_merge($ids, $_ids);
                }
            }
            $this->realtimeManager->trigger(
                RealtimeManager::PRODUCT_ENTITY,
                join(",", array_unique($ids)),
                RealtimeManager::TYPE_CHANGE_UPDATE
            );
        }

        if (OrderManagement::$SAVE_ORDER === true && $object instanceof Item) {
            $this->realtimeManager->trigger(
                RealtimeManager::PRODUCT_ENTITY,
                $object->getProduct()->getId(),
                RealtimeManager::TYPE_CHANGE_UPDATE
            );
        }

        if ($object instanceof \Magento\CatalogInventory\Model\Stock\Item) {
            if ($request && false !== strpos($request->getPathInfo(), "rest/V1/products")) {
                return;
            }
            $this->realtimeManager->trigger(
                RealtimeManager::PRODUCT_ENTITY,
                $object->getData('product_id'),
                RealtimeManager::TYPE_CHANGE_UPDATE
            );
        }
    }

    /**
     * @param \Magento\Catalog\Model\Product $product
     *
     * @return bool
     */
    protected function isDataChange(Product $product)
    {
        foreach ($this->fieldOriginalProductCheck as $field) {
            if ($product->getOrigData($field) != $product->getData($field)) {
                return true;
            }
        }
        return false;
    }
}
