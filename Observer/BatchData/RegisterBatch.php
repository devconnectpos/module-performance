<?php
/**
 * Created by IntelliJ IDEA.
 * User: vjcspy
 * Date: 4/5/17
 * Time: 2:22 PM
 */

namespace SM\Performance\Observer\BatchData;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use SM\Performance\Helper\RealtimeManager;

/**
 * Class RegisterBatch
 *
 * @package SM\Performance\Observer\BatchData
 */
class RegisterBatch implements ObserverInterface
{
    /**
     * @var \SM\Performance\Helper\RealtimeManager
     */
    private $realtimeManager;

    public function __construct(RealtimeManager $realtimeManager)
    {
        $this->realtimeManager = $realtimeManager;
    }

    /**
     * @param Observer $observer
     *
     * @return void
     * @throws \Exception
     */
    public function execute(Observer $observer)
    {
        /** @var \SM\XRetail\Controller\V1\Xretail $apiController */
        $apiController = $observer->getData('apiController');
        $path          = $apiController->getPath();

        if (in_array($path, ['take-payment', 'retail-setting'])) {
            RealtimeManager::$CAN_SEND_REAL_TIME = false;

            return;
        }

        $this->realtimeManager->useBatchData();
    }
}
