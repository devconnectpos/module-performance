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

        if ($order->getData('retail_id') || $order->getData('shipping_method') === 'smstorepickup_smstorepickup') {
            $this->realtimeManager->trigger(
                RealtimeManager::ORDER_ENTITY,
                $order->getId(),
                RealtimeManager::TYPE_CHANGE_NEW
            );
        }
    }
}
