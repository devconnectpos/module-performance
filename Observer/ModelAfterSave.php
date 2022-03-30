<?php

namespace SM\Performance\Observer;

use Magento\Catalog\Model\Category;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Type;
use Magento\ConfigurableProduct\Model\ResourceModel\Product\Type\Configurable;
use Magento\Customer\Model\Customer;
use Magento\Customer\Model\Group;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\ObjectManagerInterface;
use Magento\Quote\Model\Quote\Item;
use SM\Performance\Helper\RealtimeManager;
use SM\Sales\Repositories\OrderManagement;
use Magento\Sales\Model\ResourceModel\Order as OrderResource;
use Magento\Sales\Model\Order;
use SM\Shipping\Model\Carrier\RetailStorePickUp;

/**
 * Class ModelAfterSave
 *
 * @package SM\Performance\Observer
 */
class ModelAfterSave implements ObserverInterface
{
    const STORE_PICKUP_STATUSES
        = [
            OrderManagement::RETAIL_ORDER_PARTIALLY_PAID_AWAIT_PICKING,
            OrderManagement::RETAIL_ORDER_PARTIALLY_PAID_PICKING_IN_PROGRESS,
            OrderManagement::RETAIL_ORDER_PARTIALLY_PAID_AWAIT_COLLECTION,

            OrderManagement::RETAIL_ORDER_COMPLETE_AWAIT_PICKING,
            OrderManagement::RETAIL_ORDER_COMPLETE_PICKING_IN_PROGRESS,
            OrderManagement::RETAIL_ORDER_COMPLETE_AWAIT_COLLECTION,

            OrderManagement::RETAIL_ORDER_PARTIALLY_REFUND_AWAIT_PICKING,
            OrderManagement::RETAIL_ORDER_PARTIALLY_REFUND_PICKING_IN_PROGRESS,
            OrderManagement::RETAIL_ORDER_PARTIALLY_REFUND_AWAIT_COLLECTION,

            OrderManagement::RETAIL_ORDER_EXCHANGE_AWAIT_PICKING,
            OrderManagement::RETAIL_ORDER_EXCHANGE_PICKING_IN_PROGRESS,
            OrderManagement::RETAIL_ORDER_EXCHANGE_AWAIT_COLLECTION,
        ];

    /**
     * @var \Magento\Bundle\Model\Product\Type
     */
    protected $bundleProductType;
    /**
     * @var \Magento\GroupedProduct\Model\Product\Type\Grouped
     */
    protected $groupedProductType;
    /**
     * @var \SM\Performance\Helper\RealtimeManager
     */
    private $realtimeManager;

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
            "price",
        ];

    /**
     * Catalog product type configurable
     *
     * @var Configurable
     */
    protected $catalogProductTypeConfigurable;

    /**
     * @var OrderResource
     */
    private $orderResource;

    public static $SUPPORT_CHECK_REALTIME_API = false;

    /**
     * ModelAfterSave constructor.
     *
     * @param \SM\Performance\Helper\RealtimeManager             $realtimeManager
     * @param \Magento\Framework\ObjectManagerInterface          $objectManager
     * @param Configurable                                       $catalogProductTypeConfigurable
     * @param \Magento\Bundle\Model\Product\Type                 $bundleProductType
     * @param \Magento\GroupedProduct\Model\Product\Type\Grouped $groupedProductType
     * @param \Magento\Sales\Api\OrderRepositoryInterface        $orderRepository
     * @param OrderResource                                      $orderResource
     */
    public function __construct(
        RealtimeManager $realtimeManager,
        ObjectManagerInterface $objectManager,
        Configurable $catalogProductTypeConfigurable,
        \Magento\Bundle\Model\Product\Type $bundleProductType,
        \Magento\GroupedProduct\Model\Product\Type\Grouped $groupedProductType,
        OrderResource $orderResource
    ) {
        $this->objectManager = $objectManager;
        $this->realtimeManager = $realtimeManager;
        $this->catalogProductTypeConfigurable = $catalogProductTypeConfigurable;
        $this->bundleProductType = $bundleProductType;
        $this->groupedProductType = $groupedProductType;
        $this->orderResource = $orderResource;
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

            return;
        }

        if ($object instanceof Category) {
            $this->realtimeManager->trigger(
                RealtimeManager::CATEGORY_ENTITY,
                $object->getId(),
                RealtimeManager::TYPE_CHANGE_UPDATE
            );

            return;
        }

        // move category
        if ($observer->getData('category') instanceof Category && $observer->getData('category_id')) {
            $this->realtimeManager->trigger(
                RealtimeManager::CATEGORY_ENTITY,
                $observer->getData('category_id'),
                RealtimeManager::TYPE_CHANGE_UPDATE
            );

            return;
        }

        if ($object instanceof Group) {
            $this->realtimeManager->trigger(
                RealtimeManager::CUSTOMER_GROUP,
                $object->getData('customer_group_id'),
                RealtimeManager::TYPE_CHANGE_UPDATE
            );

            return;
        }

        if ($object instanceof Product) {
            if (ModelAfterSave::$SUPPORT_CHECK_REALTIME_API && $request && false !== strpos($request->getPathInfo(), "rest/V1/products") && !$this->isDataChange($object)) {
                return;
            }

            $ids = [];
            $ids[] = $object->getId();
            if ($object->getTypeId() === 'configurable') {
                /** @var \Magento\ConfigurableProduct\Model\Product\Type\Configurable $instanceType */
                $instanceType = $object->getTypeInstance();
                $childIds = $instanceType->getChildrenIds($object->getId());
                foreach ($childIds as $_ids) {
                    $ids += $_ids;
                }
            }
            $productType = $object->getTypeId();
            if (in_array($productType, [Type::TYPE_SIMPLE, Type::TYPE_VIRTUAL], true)) {
                $ids = array_merge(
                    $ids,
                    $this->getBundleParentIds($object->getId()),
                    $this->getGroupedParentIds($object->getId()),
                    $this->getConfigurableParentIds($object->getId())
                );
            }
            $this->realtimeManager->trigger(
                RealtimeManager::PRODUCT_ENTITY,
                implode(",", array_unique($ids)),
                RealtimeManager::TYPE_CHANGE_UPDATE
            );

            return;
        }

        if (OrderManagement::$SAVE_ORDER === true && $object instanceof Item) {
            $this->realtimeManager->trigger(
                RealtimeManager::PRODUCT_ENTITY,
                $object->getProduct()->getId(),
                RealtimeManager::TYPE_CHANGE_UPDATE
            );

            return;
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

            return;
        }

        if ($object instanceof \Magento\Sales\Model\Order) {
            $this->updateOrderRetailStatus($object);

            return;
        }

        if ($object instanceof \Magento\Sales\Model\Order\Item || $object instanceof \Magento\Sales\Model\Order\Shipment) {
            $order = $object->getOrder();
            if ($order->getId()) {
                $this->updateOrderRetailStatus($order);
            }
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

    /**
     * @param $childId
     *
     * @return string[]
     */
    public function getConfigurableParentIds($childId)
    {
        return $this->catalogProductTypeConfigurable->getParentIdsByChild($childId);
    }

    /**
     * @param $childId
     *
     * @return array
     */
    public function getBundleParentIds($childId)
    {
        return $this->bundleProductType->getParentIdsByChild($childId);
    }

    /**
     * @param $childId
     *
     * @return array
     */
    public function getGroupedParentIds($childId)
    {
        return $this->groupedProductType->getParentIdsByChild($childId);
    }

    public function updateOrderRetailStatus(Order $order)
    {
        if (!$order->getData('retail_id')) {
            if ($order->getShippingMethod() === RetailStorePickUp::METHOD) {
                if (is_null($order->getData('retail_status'))) {
                    $this->initRetailStatusForOnlinePickupOrder($order);

                    return;
                }

                $this->updateRetailStatusForOnlinePickupOrder($order);
            }

            return;
        }

        // cancelled order
        if ($order->getState() === Order::STATE_CANCELED) {
            $this->setRetailStatusToOrder($order, OrderManagement::RETAIL_ORDER_CANCELED);

            return;
        }

        // has credit memo
        if ($order->hasCreditmemos()) {
            $this->updateRetailStatusForOrderRefunded($order);

            return;
        }

        // has shipment
        if ($order->getData('retail_has_shipment')) {
            $this->updateRetailStatusForOrderHasShipment($order);

            return;
        }

        $this->updateRetailStatusForOrderHasNoShipment($order);
    }

    /**
     * @param Order $order
     *
     * @throws \Exceptionupdate
     */
    public function initRetailStatusForOnlinePickupOrder(Order $order)
    {
        if ($order->canCreditmemo() && $order->hasCreditmemos() && $order->canShip()) {
            $this->setRetailStatusToOrder($order, OrderManagement::RETAIL_ORDER_PARTIALLY_REFUND_AWAIT_PICKING);

            return;
        }

        if ($order->canShip()) {
            if ($order->canInvoice()) {
                $this->setRetailStatusToOrder($order, OrderManagement::RETAIL_ORDER_PARTIALLY_PAID_AWAIT_PICKING);

                return;
            }

            $this->setRetailStatusToOrder($order, OrderManagement::RETAIL_ORDER_COMPLETE_AWAIT_PICKING);

            return;
        }

        if ($order->canInvoice()) {
            $this->setRetailStatusToOrder($order, OrderManagement::RETAIL_ORDER_PARTIALLY_PAID_SHIPPED);

            return;
        }

        $this->setRetailStatusToOrder($order, OrderManagement::RETAIL_ORDER_COMPLETE_SHIPPED);
    }

    public function updateRetailStatusForOnlinePickupOrder(Order $order)
    {
        $retailStatus = (int)$order->getData('retail_status');

        // Only process pick up statuses
        if (!in_array($retailStatus, self::STORE_PICKUP_STATUSES, true)) {
            return;
        }

        switch ($retailStatus) {
            case OrderManagement::RETAIL_ORDER_PARTIALLY_PAID_AWAIT_PICKING:
            case OrderManagement::RETAIL_ORDER_PARTIALLY_PAID_PICKING_IN_PROGRESS:
            case OrderManagement::RETAIL_ORDER_PARTIALLY_PAID_AWAIT_COLLECTION:
                if (!$order->canShip()) {
                    if (!$order->canInvoice()) {
                        $this->setRetailStatusToOrder($order, OrderManagement::RETAIL_ORDER_COMPLETE_SHIPPED);

                        return;
                    }

                    $this->setRetailStatusToOrder($order, OrderManagement::RETAIL_ORDER_PARTIALLY_PAID_SHIPPED);

                    return;
                }

                // Can ship
                if (!$order->canInvoice()) {
                    $this->setRetailStatusToOrder($order, OrderManagement::RETAIL_ORDER_COMPLETE_AWAIT_PICKING);
                }

                return;
            case OrderManagement::RETAIL_ORDER_COMPLETE_AWAIT_PICKING:
            case OrderManagement::RETAIL_ORDER_COMPLETE_PICKING_IN_PROGRESS:
            case OrderManagement::RETAIL_ORDER_COMPLETE_AWAIT_COLLECTION:
                if (!$order->canShip()) {
                    $this->setRetailStatusToOrder($order, OrderManagement::RETAIL_ORDER_COMPLETE_SHIPPED);
                }

                return;
            case OrderManagement::RETAIL_ORDER_PARTIALLY_REFUND_AWAIT_PICKING:
            case OrderManagement::RETAIL_ORDER_PARTIALLY_REFUND_PICKING_IN_PROGRESS:
            case OrderManagement::RETAIL_ORDER_PARTIALLY_REFUND_AWAIT_COLLECTION:
                if (!$order->canCreditmemo()) {
                    $this->setRetailStatusToOrder($order, OrderManagement::RETAIL_ORDER_FULLY_REFUND);
                }

                return;
        }
    }

    /**
     * @param Order $order
     *
     * @return Order
     * @throws \Exception
     */
    public function updateRetailStatusForOrderRefunded(Order $order)
    {
        if ($order->getData('retail_has_shipment')) { //order has shipment
            if ($order->getShippingMethod() === RetailStorePickUp::METHOD) {
                return $this->updateRetailStatusStorePickupForOrderRefunded($order);
            }

            if ($order->hasShipments()) {
                if ($order->canShip()) { //order was partially shipped
                    if ($order->canCreditmemo()) { //order was partially refunded
                        return $this->setRetailStatusToOrder($order, OrderManagement::RETAIL_ORDER_PARTIALLY_REFUND_NOT_SHIPPED);
                    }

                    //order was fully refunded
                    return $this->setRetailStatusToOrder($order, OrderManagement::RETAIL_ORDER_FULLY_REFUND);
                }

                //order was fully shipped
                if ($order->canCreditmemo()) { //order was partially refunded
                    return $this->setRetailStatusToOrder($order, OrderManagement::RETAIL_ORDER_PARTIALLY_REFUND_SHIPPED);
                }

                //order was fully refunded
                return $this->setRetailStatusToOrder($order, OrderManagement::RETAIL_ORDER_FULLY_REFUND);
            }

            //order was not shipped
            if ($order->canCreditmemo()) { //order was partially refunded
                return $this->setRetailStatusToOrder($order, OrderManagement::RETAIL_ORDER_PARTIALLY_REFUND_NOT_SHIPPED);
            }

            //order was fully refunded
            return $this->setRetailStatusToOrder($order, OrderManagement::RETAIL_ORDER_FULLY_REFUND);
        }

        //order has no shipment
        if ($order->canCreditmemo()) { //order was partially refunded
            return $this->setRetailStatusToOrder($order, OrderManagement::RETAIL_ORDER_PARTIALLY_REFUND);
        }

        //order was fully refunded
        return $this->setRetailStatusToOrder($order, OrderManagement::RETAIL_ORDER_FULLY_REFUND);
    }

    /**
     * @param Order $order
     *
     * @return Order
     * @throws \Exception
     */
    private function updateRetailStatusStorePickupForOrderRefunded(Order $order)
    {
        $retailStatus = $order->getData('retail_status');
        if (!is_null($retailStatus) && in_array($retailStatus, self::STORE_PICKUP_STATUSES, false)) {
            return $order;
        }

        if ($order->canShip()) {
            return $this->setRetailStatusToOrder($order, OrderManagement::RETAIL_ORDER_PARTIALLY_REFUND_AWAIT_PICKING);
        }

        if ($order->canCreditmemo()) {
            return $this->setRetailStatusToOrder($order, OrderManagement::RETAIL_ORDER_PARTIALLY_REFUND_SHIPPED);
        }

        return $this->setRetailStatusToOrder($order, OrderManagement::RETAIL_ORDER_FULLY_REFUND);
    }

    /**
     * @param Order $order
     *
     * @return Order
     * @throws \Exception
     */
    public function updateRetailStatusForOrderHasShipment(Order $order)
    {
        if ($order->getShippingMethod() === RetailStorePickUp::METHOD) {
            return $this->updateRetailStatusStorePickupForOrderHasShipment($order);
        }

        $isInvoiced = !$order->canInvoice() && $order->hasInvoices();
        $isShipped = !$order->canShip() && $order->hasShipments();
        $isExchange = $order->getData('is_exchange');

        if ($isInvoiced) {
            if ($isShipped) {
                if ($isExchange) {
                    return $this->setRetailStatusToOrder($order, OrderManagement::RETAIL_ORDER_EXCHANGE_SHIPPED);
                }

                return $this->setRetailStatusToOrder($order, OrderManagement::RETAIL_ORDER_COMPLETE_SHIPPED);
            }

            if ($isExchange) {
                return $this->setRetailStatusToOrder($order, OrderManagement::RETAIL_ORDER_EXCHANGE_NOT_SHIPPED);
            }

            return $this->setRetailStatusToOrder($order, OrderManagement::RETAIL_ORDER_COMPLETE_NOT_SHIPPED);
        }

        if ($isShipped) {
            return $this->setRetailStatusToOrder($order, OrderManagement::RETAIL_ORDER_PARTIALLY_PAID_SHIPPED);
        }

        return $this->setRetailStatusToOrder($order, OrderManagement::RETAIL_ORDER_PARTIALLY_PAID_NOT_SHIPPED);
    }

    /**
     * @param Order $order
     *
     * @return Order
     * @throws \Exception
     */
    public function updateRetailStatusStorePickupForOrderHasShipment(Order $order)
    {
        $retailStatus = $order->getData('retail_status');
        if (!is_null($retailStatus) && in_array($retailStatus, self::STORE_PICKUP_STATUSES, false)) {
            return $order;
        }

        if ($order->canInvoice()) {
            if ($order->canShip()) {
                return $this->setRetailStatusToOrder($order, OrderManagement::RETAIL_ORDER_PARTIALLY_PAID_AWAIT_PICKING);
            }

            return $this->setRetailStatusToOrder($order, OrderManagement::RETAIL_ORDER_PARTIALLY_PAID_SHIPPED);
        }

        if ($order->canShip()) {
            return $this->setRetailStatusToOrder($order, OrderManagement::RETAIL_ORDER_COMPLETE_AWAIT_PICKING);
        }

        return $this->setRetailStatusToOrder($order, OrderManagement::RETAIL_ORDER_COMPLETE_SHIPPED);
    }

    /**
     * @param Order $order
     *
     * @return Order
     * @throws \Exception
     */
    public function updateRetailStatusForOrderHasNoShipment(Order $order)
    {
        $isInvoiced = !$order->canInvoice() && $order->hasInvoices();
        $isExchange = $order->getData('is_exchange');

        if ($isInvoiced) {
            if ($isExchange) {
                return $order;
            }

            return $this->setRetailStatusToOrder($order, OrderManagement::RETAIL_ORDER_COMPLETE);
        }

        return $this->setRetailStatusToOrder($order, OrderManagement::RETAIL_ORDER_PARTIALLY_PAID);
    }

    /**
     * @param Order  $order
     * @param string $retailStatus
     *
     * @return Order
     * @throws \Exception
     */
    private function setRetailStatusToOrder(Order $order, $retailStatus)
    {
        if ($order->getData('retail_status') === $retailStatus) {
            return $order;
        }

        $order->setData('retail_status', $retailStatus);
        $this->orderResource->saveAttribute($order, 'retail_status');

        return $order;
    }
}
