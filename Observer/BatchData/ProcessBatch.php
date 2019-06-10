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

    public function __construct(
        RealtimeManager $realtimeManager,
        Process $process,
        Loader $loader
    ) {
        $this->realtimeManager = $realtimeManager;
        $this->process         = $process;
        $this->configLoader  = $loader;
    }

    /**
     * @param Observer $observer
     *
     * @return void
     */
    public function execute(Observer $observer)
    {
        if (count($this->realtimeManager->getBatchData()) > 0) {
            $config = $this->configLoader->getConfigByPath('xpos/advance', 'default', 0);
            if (isset($config['xpos/advance/sync_realtime'])
                && $config['xpos/advance/sync_realtime']['value'] == 'cronjob') {
                $this->realtimeManager->processBatchData();
            } else {
                if (function_exists('exec')) {
                    $this->process
                        ->setCommand("bin/magento retail:sendrealtime " . "'" . json_encode($this->realtimeManager->getBatchData()) . "'")
                        ->start();
                } else {
                    $this->realtimeManager->getSenderInstance()->sendMessages($this->realtimeManager->getBatchData());
                }
            }
        }
    }
}
