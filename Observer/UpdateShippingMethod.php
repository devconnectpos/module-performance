<?php
/**
 * Created by PhpStorm.
 * User: xuantung
 * Date: 10/11/18
 * Time: 2:32 PM
 */

namespace SM\Performance\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Shipping\Model\Config;
use SM\Performance\Helper\RealtimeManager;
use SM\Sales\Repositories\OrderManagement;

class UpdateShippingMethod implements ObserverInterface
{

    /**
     * @var \SM\Performance\Helper\RealtimeManager
     */
    private $realtimeManager;

    protected $shippingConfig;

    /**
     * UpdateShippingMethod constructor.
     * @param RealtimeManager $realtimeManager
     * @param \Magento\Shipping\Model\Config $shippingConfig
     */
    public function __construct(
        RealtimeManager $realtimeManager,
        Config $shippingConfig
    ) {
        $this->shippingConfig = $shippingConfig;
        $this->realtimeManager = $realtimeManager;
    }

    /**
     * @param \Magento\Framework\Event\Observer $observer
     *
     * @throws \Exception
     */
    public function execute(Observer $observer)
    {
        $activeCarriers = $this->shippingConfig->getAllCarriers();
        $codes = [];
        foreach ($activeCarriers as $carrierCode => $carrierModel) {
            if (in_array($carrierCode, OrderManagement::getAllowedShippingMethods())) {
                $codes[] = $carrierCode;
            }
        }
        $entity_id = 'all';
        if (!empty($codes)) {
            $entity_id = join(",", array_unique($codes));
        }
        $this->realtimeManager->trigger(
            RealtimeManager::SHIPPING_METHOD,
            $entity_id,
            RealtimeManager::TYPE_CHANGE_UPDATE
        );
    }
}
