<?php
/**
 * Created by IntelliJ IDEA.
 * User: vjcspy
 * Date: 4/5/17
 * Time: 2:24 PM
 */

namespace SM\Performance\Observer\BatchData;

use Magento\Config\Model\Config\Loader;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Serialize\Serializer\Json;
use SM\Performance\Helper\RealtimeManager;
use SM\XRetail\Model\Shell\Process;

class ProcessBatch implements ObserverInterface
{
    /**
     * @var \SM\Performance\Helper\RealtimeManager
     */
    private $realtimeManager;
    /**
     * @var \SM\XRetail\Model\Shell\Process
     */
    private $process;
    /**
     * @var \Magento\Config\Model\Config\Loader
     */
    private $configLoader;

    /**
     * @var Json
     */
    protected $jsonSerializer;

    public function __construct(
        RealtimeManager $realtimeManager,
        Process $process,
        Loader $loader,
        Json $jsonSerializer
    ) {
        $this->realtimeManager = $realtimeManager;
        $this->process = $process;
        $this->configLoader = $loader;
        $this->jsonSerializer = $jsonSerializer;
    }

    /**
     * @param Observer $observer
     *
     * @return void
     */
    public function execute(Observer $observer)
    {
        $batchData = $this->realtimeManager->getBatchData();
        if (count($batchData) === 0) {
            return;
        }

        $entity = isset($batchData[0]) ? $batchData[0]['entity'] : '';
        $typeChange = isset($batchData[0]) ? $batchData[0]['type_change'] : '';

        $config = $this->configLoader->getConfigByPath('xpos/advance', 'default', 0);
        $realtimeConfig = isset($config['xpos/advance/sync_realtime']) ? $config['xpos/advance/sync_realtime']['value'] : '';

        // When it is a manual mode of entities other than product
        $manualModeNotProduct = ($realtimeConfig === 'manual' && $entity !== RealtimeManager::PRODUCT_ENTITY);
        // When it is a manual mode of product but it is not product update (e.g. new or delete action)
        $manualModeProductNotUpdate = ($realtimeConfig === 'manual' && $entity === RealtimeManager::PRODUCT_ENTITY && $typeChange !== RealtimeManager::TYPE_CHANGE_UPDATE);

        if ($realtimeConfig === 'cronjob') {
            $this->realtimeManager->processBatchData();
        } elseif ($realtimeConfig === 'immediately' || $manualModeNotProduct || $manualModeProductNotUpdate) {
            if (function_exists('exec')) {
                $this->process
                    ->setCommand("bin/magento cpos:sendrealtime "."'".$this->jsonSerializer->serialize($batchData)."'")
                    ->start();
            } else {
                $this->realtimeManager->getSenderInstance()->sendMessages($batchData);
            }
        }
    }
}
