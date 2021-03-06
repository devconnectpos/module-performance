<?php
/**
 * Created by IntelliJ IDEA.
 * User: vjcspy
 * Date: 13/03/2017
 * Time: 14:31
 */

namespace SM\Performance\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Api\Data\CreditmemoInterface;
use SM\Performance\Helper\RealtimeManager;

class AfterRefund implements ObserverInterface
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
        /** @var CreditmemoInterface $creditmemo */
        $creditmemo = $observer->getData('creditmemo');
        if ($creditmemo->getOrderId()) {
            $this->realtimeManager->trigger(
                RealtimeManager::ORDER_ENTITY,
                $creditmemo->getOrderId(),
                RealtimeManager::TYPE_CHANGE_UPDATE
            );
        }
        if ($creditmemo->getAllItems()) {
            $items = $creditmemo->getAllItems();
            $ids = [];
            foreach ($items as $item) {
                array_push($ids, $item->getProductId());
            }
            $this->realtimeManager->trigger(
                RealtimeManager::PRODUCT_ENTITY,
                join(",", array_unique($ids)),
                RealtimeManager::TYPE_CHANGE_UPDATE
            );
        }
    }
}
