<?php
namespace SM\Performance\Plugin;

use SM\Performance\Helper\RealtimeManager;


/**
 * Class RealTimeTax
 *
 * @package SM\Performance\Plugin
 */
class UpdateAttributes {

    /**
     * @var \SM\Performance\Helper\RealtimeManager
     */
    protected $realtimeManager;

    /**
     * RealTimeTax constructor.
     *
     * @param \SM\Performance\Helper\RealtimeManager $realtimeManager
     */
    public function __construct(
        \SM\Performance\Helper\RealtimeManager $realtimeManager
    ) {
        $this->realtimeManager = $realtimeManager;
    }

    /**
     * @param $subject
     * @param $result
     *
     * @return string
     */
    public function afterUpdateAttributes($subject, $result, $productId, $attrData, $storeId) {

        foreach ($productId as $product){
            $this->realtimeManager->trigger(RealtimeManager::PRODUCT_ENTITY, $product, RealtimeManager::TYPE_CHANGE_UPDATE);
        }


        return $result;
    }

}
