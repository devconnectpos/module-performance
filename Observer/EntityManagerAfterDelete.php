<?php


namespace SM\Performance\Observer;


class EntityManagerAfterDelete implements \Magento\Framework\Event\ObserverInterface
{

    /**
     * @var \SM\Performance\Helper\RealtimeManager
     */
    private $realtimeManager;

    public function __construct(\SM\Performance\Helper\RealtimeManager $realtimeManager)
    {
        $this->realtimeManager = $realtimeManager;
    }

    /**
     * @inheritDoc
     */
    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        $entity = $observer->getData('entity');

        if ($entity instanceof \Aheadworks\Giftcard\Api\Data\PoolInterface) {
            $entityId = $entity->getData('id');
            $this->realtimeManager->trigger(
                \SM\Performance\Helper\RealtimeManager::AW_CODE_POOL,
                $entityId,
                \SM\Performance\Helper\RealtimeManager::TYPE_CHANGE_REMOVE
            );
        }

        if ($entity instanceof \Aheadworks\Giftcard\Api\Data\Pool\CodeInterface) {
            $poolId = $entity->getPoolId();
            $this->realtimeManager->trigger(
                \SM\Performance\Helper\RealtimeManager::AW_CODE_POOL,
                $poolId,
                \SM\Performance\Helper\RealtimeManager::TYPE_CHANGE_UPDATE
            );
        }
    }
}
