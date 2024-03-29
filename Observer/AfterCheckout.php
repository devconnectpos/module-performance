<?php
/**
 * Created by mr.vjcspy@gmail.com - khoild@smartosc.com.
 * Date: 08/03/2017
 * Time: 11:35
 */

namespace SM\Performance\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Model\Order;
use SM\Performance\Helper\RealtimeManager;
use SM\Shipping\Model\Carrier\RetailStorePickUp;

/**
 * Class AfterCheckout
 *
 * @package SM\Performance\Observer
 */
class AfterCheckout implements ObserverInterface
{

    /**
     * @var \SM\Performance\Helper\RealtimeManager
     */
    private $realtimeManager;

    /**
     * AfterCheckout constructor.
     *
     * @param \SM\Performance\Helper\RealtimeManager $realtimeManager
     */
    public function __construct(
        RealtimeManager $realtimeManager
    ) {
        $this->realtimeManager = $realtimeManager;
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
        	$entityId = $order->getId();
        	if ($order->getData('origin_order_id')) {
        		$entityId = join(',', [$order->getId(), $order->getData('origin_order_id')]);
	        }
            $this->realtimeManager->trigger(
                RealtimeManager::ORDER_ENTITY,
	            $entityId,
                RealtimeManager::TYPE_CHANGE_NEW
            );
        }
    }
}
