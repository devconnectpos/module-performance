<?php
/**
 * Created by IntelliJ IDEA.
 * User: vjcspy
 * Date: 14/03/2017
 * Time: 18:08
 */

namespace SM\Performance\Observer;

use Exception;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use SM\Performance\Helper\RealtimeManager;
use SM\Integrate\Helper\Data as IntegrateHelper;

class AfterShipment implements ObserverInterface
{

    /**
     * @var \SM\Performance\Helper\RealtimeManager
     */
    private $realtimeManager;
    /**
     * @var \SM\Integrate\Helper\Data
     */
    protected $integrateHelperData;

    /**
     * AfterCheckout constructor.
     *
     * @param \SM\Performance\Helper\RealtimeManager $realtimeManager
     * @param \SM\Integrate\Helper\Data              $integrateHelperData
     */
    public function __construct(
        RealtimeManager $realtimeManager,
        IntegrateHelper $integrateHelperData
    ) {
        $this->realtimeManager = $realtimeManager;
        $this->integrateHelperData    = $integrateHelperData;
    }

    /**
     * @param \Magento\Framework\Event\Observer $observer
     *
     * @throws \Exception
     */
    public function execute(Observer $observer)
    {
        $shipment = $observer->getEvent()->getShipment();
        $order = $shipment->getOrder();

        try {
            $this->realtimeManager->trigger(
                RealtimeManager::ORDER_ENTITY,
                $order->getId(),
                RealtimeManager::TYPE_CHANGE_NEW
            );
            if ($this->integrateHelperData->isMagentoInventory()) {
                $items = $order->getAllItems();
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
        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }
    }
}
