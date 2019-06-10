<?php
/**
 * Created by IntelliJ IDEA.
 * User: vjcspy
 * Date: 13/03/2017
 * Time: 14:29
 */

namespace SM\Performance\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Model\Order;
use SM\Performance\Helper\RealtimeManager;

class InvoiceRegister implements ObserverInterface
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
