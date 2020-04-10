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

class UpdateShippingMethod implements ObserverInterface
{

    /**
     * @var \SM\Performance\Helper\RealtimeManager
     */
    private $realtimeManager;

    protected $shippingConfig;
	/**
	 * @var \SM\Shipping\Helper\Shipping
	 */
	private $shippingHelper;
	
	/**
	 * UpdateShippingMethod constructor.
	 * @param RealtimeManager $realtimeManager
	 * @param \Magento\Shipping\Model\Config $shippingConfig
	 * @param \SM\Shipping\Helper\Shipping $shippingHelper
	 */
    public function __construct(
        RealtimeManager $realtimeManager,
        Config $shippingConfig,
        \SM\Shipping\Helper\Shipping $shippingHelper
    ) {
        $this->shippingConfig = $shippingConfig;
        $this->realtimeManager = $realtimeManager;
	    $this->shippingHelper = $shippingHelper;
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
            if (in_array($carrierCode, $this->shippingHelper->getAllowedShippingMethods())) {
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
