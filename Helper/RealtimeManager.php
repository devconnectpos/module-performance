<?php
/**
 * Created by mr.vjcspy@gmail.com - khoild@smartosc.com.
 * Date: 08/03/2017
 * Time: 11:04
 */

namespace SM\Performance\Helper;

use Magento\Config\Model\Config\Loader;
use Magento\Framework\ObjectManagerInterface;
use Magento\Setup\Exception;
use Psr\Log\LoggerInterface;
use SM\Performance\Model\RealtimeStorageFactory;
use SM\XRetail\Helper\Data;
use SM\XRetail\Model\Shell\Process;

/**
 * Class RealtimeManager
 *
 * @package SM\Performance\Helper
 */
class RealtimeManager
{

    public static $CAN_SEND_REAL_TIME = true;
    public static $USE_ASYNC          = true;

    const ORDER_ENTITY    = "orders";
    const PRODUCT_ENTITY  = "products";
    const CATEGORY_ENTITY = "category";
    const CUSTOMER_ENTITY = "customers";
    const CUSTOMER_GROUP  = "customerGroup";
    const SETTING_ENTITY  = "settings";
    const TAX_ENTITY      = "taxes";
    const SHIPPING_METHOD   = "shippingMethods";

    const TYPE_CHANGE_NEW    = 'new';
    const TYPE_CHANGE_UPDATE = 'update';
    const TYPE_CHANGE_REMOVE = 'remove';

    /**
     * @var \SM\Performance\Gateway\Sender
     */
    protected static $senderInstance;

    /**
     * @var bool
     */
    protected static $useBatch = false;

    /**
     * @var array
     */
    protected static $batchData = [];
    /**
     * @var \SM\XRetail\Model\Shell\Process
     */
    private $process;

    /**
     * @var \SM\XRetail\Helper\Data
     */
    private $retailHelper;

    /**
     * @var \SM\Performance\Model\RealtimeStorageFactory
     */
    protected $realtimeStorageFactory;

    /**
     * @var \Magento\Config\Model\Config\Loader
     */
    private $configLoader;

    /**
     * @var \Psr\Log\LoggerInterface
     */
    private $logger;

    /**
     * RealtimeManager constructor.
     *
     * @param \Magento\Framework\ObjectManagerInterface    $objectManager
     * @param \SM\XRetail\Model\Shell\Process              $process
     * @param \SM\XRetail\Helper\Data                      $retailHelper
     * @param \SM\Performance\Model\RealtimeStorageFactory $realtimeStorageFactory
     * @param \Magento\Config\Model\Config\Loader          $loader
     * @param \Psr\Log\LoggerInterface                     $logger
     */
    public function __construct(
        ObjectManagerInterface $objectManager,
        Process $process,
        Data $retailHelper,
        RealtimeStorageFactory $realtimeStorageFactory,
        Loader $loader,
        LoggerInterface $logger
    ) {
        $this->objectManager = $objectManager;
        $this->process       = $process;
        $this->retailHelper  = $retailHelper;
        $this->logger        = $logger;
        $this->configLoader  = $loader;
        $this->realtimeStorageFactory  = $realtimeStorageFactory;
    }

    /**
     * @param $entity
     * @param $entityId
     * @param $typeChange
     *
     * @throws \Exception
     */
    public function trigger($entity, $entityId, $typeChange)
    {
        if (!RealtimeManager::$CAN_SEND_REAL_TIME) {
            return;
        }

        if (is_null(RealtimeManager::$senderInstance)) {
            RealtimeManager::$senderInstance = $this->objectManager->create('SM\Performance\Gateway\Sender');
        }
        if (!RealtimeManager::$useBatch) {
            // realtime from server magento to connectpos
            $config = $this->configLoader->getConfigByPath('xpos/advance', 'default', 0);
            if (isset($config['xpos/advance/sync_realtime'])
                && $config['xpos/advance/sync_realtime']['value'] == 'cronjob') {
                $dataRealtime = [
                    [
                        'entity'      => $entity,
                        'entity_id'   => $entityId,
                        'type_change' => $typeChange
                    ]
                ];
                if (function_exists('exec')) {
                    $this->saveDataRealtime($dataRealtime);
                } else {
                    RealtimeManager::$senderInstance->sendMessages($dataRealtime);
                }

            } else {
                // if php exec is enable
                if (function_exists('exec')) {
                    $this->process
                        ->setCommand(
                            "bin/magento retail:sendrealtime " . "'" . json_encode(
                                [
                                    [
                                        'entity'      => $entity,
                                        'entity_id'   => $entityId,
                                        'type_change' => $typeChange
                                    ]
                                ]
                            ) . "'"
                        )
                        ->start();
                } else {
                    $dataRealtime = [
                        [
                            'entity'      => $entity,
                            'entity_id'   => $entityId,
                            'type_change' => $typeChange
                        ]
                    ];

                    RealtimeManager::$senderInstance->sendMessages($dataRealtime);
                }

            }
        } else {
            // realtime from connectpos to server magento
            $this->pushToBatch($entity, $entityId, $typeChange);
        }
    }

    /**
     * method save data sync realtime
     *
     * @param $dataRealtime array
     *
     * @throws \Exception
     */
    public function saveDataRealtime($dataRealtime)
    {
        try {
            $created_at         = $this->retailHelper->getCurrentTime();
            $realtimeModel      = $this->realtimeStorageFactory->create();
            $transactionData    = [
                "data_realtime" => json_encode($dataRealtime),
                "creation_time" => $created_at
            ];

            $realtimeModel->addData($transactionData)->save();
        } catch (Exception $e) {
            throw new \Exception("Error save data realtime: " . $e->getMessage());
        }
    }

    /**
     * @return \SM\Performance\Gateway\Sender
     */
    public function getSenderInstance()
    {
        if (is_null(RealtimeManager::$senderInstance)) {
            RealtimeManager::$senderInstance = $this->objectManager->create('SM\Performance\Gateway\Sender');
        }

        return RealtimeManager::$senderInstance;
    }

    /**
     * @param $entity
     * @param $entityId
     * @param $typeChange
     *
     * @return $this
     */
    protected function pushToBatch($entity, $entityId, $typeChange)
    {
        RealtimeManager::$batchData[] = [
            'entity'      => $entity,
            'entity_id'   => $entityId,
            'type_change' => $typeChange
        ];

        return $this;
    }

    /**
     * @return array
     */
    public function getBatchData()
    {
        return RealtimeManager::$batchData;
    }

    /**
     * @return $this
     * @throws \Exception
     */
    public function processBatchData()
    {
        if (RealtimeManager::$_useBatch === true && !is_null(RealtimeManager::$senderInstance)) {
            if (function_exists('exec')) {
                $this->saveDataRealtime($this->getBatchData());
            } else {
                RealtimeManager::$senderInstance->sendMessages($this->getBatchData());
            }
        }

        return $this;
    }

    /**
     * @return $this
     */
    public function useBatchData()
    {
        if (RealtimeManager::$useBatch !== true) {
            RealtimeManager::$useBatch  = true;
            RealtimeManager::$batchData = [];
        }

        return $this;
    }
}
