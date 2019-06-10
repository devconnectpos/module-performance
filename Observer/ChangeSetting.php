<?php
/**
 * Created by IntelliJ IDEA.
 * User: vjcspy
 * Date: 13/03/2017
 * Time: 14:39
 */

namespace SM\Performance\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use SM\Performance\Helper\RealtimeManager;

class ChangeSetting implements ObserverInterface
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
        $this->realtimeManager->trigger(RealtimeManager::SETTING_ENTITY, "all", RealtimeManager::TYPE_CHANGE_UPDATE);
    }
}
