<?php

namespace SM\Performance\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Model\Order;
use SM\Performance\Helper\RealtimeManager;
use SM\Sales\Repositories\OrderManagement;
use SM\Shipping\Model\Carrier\RetailStorePickUp;

class AfterCancelOrder implements ObserverInterface
{
    /**
     * @var \SM\Performance\Helper\RealtimeManager
     */
    private $realtimeManager;

    /**
     * @var \Magento\Sales\Api\OrderRepositoryInterface
     */
    private $orderRepository;

    /**
     * AfterCancelOrder constructor.
     *
     * @param \SM\Performance\Helper\RealtimeManager      $realtimeManager
     * @param \Magento\Sales\Api\OrderRepositoryInterface $orderRepository
     */
    public function __construct(
        RealtimeManager $realtimeManager,
        \Magento\Sales\Api\OrderRepositoryInterface $orderRepository
    ) {
        $this->realtimeManager = $realtimeManager;
        $this->orderRepository = $orderRepository;
    }

    /**
     * @param \Magento\Framework\Event\Observer $observer
     *
     * @throws \Exception
     */
    public function execute(Observer $observer)
    {
        /** @var Order $order */
        $order = $observer->getData('order');
        $shippingMethod = $order->getData('shipping_method');
        $storePickupMethods = [
            RetailStorePickUp::METHOD,
            'mageworxpickup_mageworxpickup'
        ];

        if ($order->getData('retail_id') ||
            in_array($shippingMethod, $storePickupMethods, true) ||
            $order->getData('is_pwa') === 1) {

            if ($order instanceof \Magento\Sales\Model\Order) {
                $this->updateOrderRetailStatus($order);
            }

            $this->realtimeManager->trigger(
                RealtimeManager::ORDER_ENTITY,
                $order->getId(),
                RealtimeManager::TYPE_CHANGE_UPDATE
            );
        }
    }

    /**
     * @param \Magento\Sales\Model\Order $order
     */
    protected function updateOrderRetailStatus(Order $order)
    {
        if (!$order->getData('retail_id')) {
            return;
        }
        if ($order->getState() == Order::STATE_CANCELED) {
            $order->setData('retail_status', OrderManagement::RETAIL_ORDER_CANCELED);
        }

        $this->orderRepository->save($order);
    }
}
