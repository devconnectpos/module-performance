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
use SM\Performance\Gateway\Sender;
use SM\Core\Logger\Logger as CposLogger;

/**
 * Class RealtimeManager
 *
 * @package SM\Performance\Helper
 */
class RealtimeManager
{
    public static $CAN_SEND_REAL_TIME = true;
    public static $USE_ASYNC = true;

    const ORDER_ENTITY = "orders";
    const PRODUCT_ENTITY = "products";
    const CATEGORY_ENTITY = "category";
    const CUSTOMER_ENTITY = "customers";
    const CUSTOMER_GROUP = "customerGroup";
    const SETTING_ENTITY = "settings";
    const TAX_ENTITY = "taxes";
    const SHIPPING_METHOD = "shippingMethods";
    const AW_CODE_POOL = "awCodePool";

    const TYPE_CHANGE_NEW = 'new';
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
     * @var CposLogger
     */
    private $cposLogger;

    /**
     * RealtimeManager constructor.
     *
     * @param \Magento\Framework\ObjectManagerInterface    $objectManager
     * @param \SM\XRetail\Model\Shell\Process              $process
     * @param \SM\XRetail\Helper\Data                      $retailHelper
     * @param \SM\Performance\Model\RealtimeStorageFactory $realtimeStorageFactory
     * @param \Magento\Config\Model\Config\Loader          $loader
     * @param \Psr\Log\LoggerInterface                     $logger
     * @param CposLogger                                   $cposLogger
     */
    public function __construct(
        ObjectManagerInterface $objectManager,
        Process $process,
        Data $retailHelper,
        RealtimeStorageFactory $realtimeStorageFactory,
        Loader $loader,
        LoggerInterface $logger,
        CposLogger $cposLogger
    ) {
        $this->objectManager = $objectManager;
        $this->process = $process;
        $this->retailHelper = $retailHelper;
        $this->logger = $logger;
        $this->configLoader = $loader;
        $this->realtimeStorageFactory = $realtimeStorageFactory;
        $this->cposLogger = $cposLogger;
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
        if (!self::$CAN_SEND_REAL_TIME) {
            return;
        }

        if (is_null(self::$senderInstance)) {
            self::$senderInstance = $this->objectManager->create(Sender::class);
        }
        if (!self::$useBatch) {
            // realtime from server magento to connectpos
            $config = $this->configLoader->getConfigByPath('xpos/advance', 'default', 0);
            $realtimeConfig = isset($config['xpos/advance/sync_realtime']) ? $config['xpos/advance/sync_realtime']['value'] : '';

            // When it is a manual mode of entities other than product
            $manualModeNotProduct = ($realtimeConfig === 'manual' && $entity !== RealtimeManager::PRODUCT_ENTITY);
            // When it is a manual mode of product but it is not product update (e.g. new or delete action)
            $manualModeProductNotUpdate = ($realtimeConfig === 'manual' && $entity === RealtimeManager::PRODUCT_ENTITY && $typeChange !== RealtimeManager::TYPE_CHANGE_UPDATE);

            // Log product synchronization
            $this->cposLogger->info("===> [CPOS] Realtime synchronization for entity ".$entity." with ID ".$entityId." at ".date('Y-m-d H:i:s'));

            if ($realtimeConfig === 'cronjob') {
                $dataRealtime = [
                    [
                        'entity'      => $entity,
                        'entity_id'   => $entityId,
                        'type_change' => $typeChange,
                    ],
                ];
                if (function_exists('exec')) {
                    $this->saveDataRealtime($dataRealtime);
                } else {
                    self::$senderInstance->sendMessages($dataRealtime);
                }
            } elseif ($realtimeConfig === 'immediately' || $realtimeConfig === 'no_product_cache' || $manualModeNotProduct || $manualModeProductNotUpdate) {
                // MANUAL TRIGGER ONLY APPLIES FOR PRODUCT ENTITY!
                // if php exec is enabled
                usleep(random_int(500, 5000)); // Delay execution, potentially prevent database overload
                if (function_exists('exec')) {
                    $this->process
                        ->setCommand(
                            "bin/magento cpos:sendrealtime "."'".json_encode([
                                [
                                    'entity'      => $entity,
                                    'entity_id'   => $entityId,
                                    'type_change' => $typeChange,
                                ],
                            ])."'"
                        )
                        ->start();
                } else {
                    $dataRealtime = [
                        [
                            'entity'      => $entity,
                            'entity_id'   => $entityId,
                            'type_change' => $typeChange,
                        ],
                    ];

                    self::$senderInstance->sendMessages($dataRealtime);
                }
            } else {

            }
        }
    }

    /**
     * @param $entity
     * @param $entityId
     * @param $typeChange
     *
     * @throws \Exception
     */
    public function triggerForce($entity, $entityId, $typeChange)
    {
        if (is_null(self::$senderInstance)) {
            self::$senderInstance = $this->objectManager->create(Sender::class);
        }

        // if php exec is enable
        if (function_exists('exec')) {
            $this->process
                ->setCommand(
                    "bin/magento cpos:sendrealtime "."'".json_encode([
                        [
                            'entity'      => $entity,
                            'entity_id'   => $entityId,
                            'type_change' => $typeChange,
                        ],
                    ])."'"
                )
                ->start();
        } else {
            $dataRealtime = [
                [
                    'entity'      => $entity,
                    'entity_id'   => $entityId,
                    'type_change' => $typeChange,
                ],
            ];

            self::$senderInstance->sendMessages($dataRealtime);
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
            $created_at = $this->retailHelper->getCurrentTime();
            $realtimeModel = $this->realtimeStorageFactory->create();
            $transactionData = [
                "data_realtime" => json_encode($dataRealtime),
                "creation_time" => $created_at,
            ];

            $realtimeModel->addData($transactionData)->save();
        } catch (Exception $e) {
            throw new \Exception("Error save data realtime: ".$e->getMessage());
        }
    }

    /**
     * @return \SM\Performance\Gateway\Sender
     */
    public function getSenderInstance()
    {
        if (is_null(self::$senderInstance)) {
            self::$senderInstance = $this->objectManager->create('SM\Performance\Gateway\Sender');
        }

        return self::$senderInstance;
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
        self::$batchData[] = [
            'entity'      => $entity,
            'entity_id'   => $entityId,
            'type_change' => $typeChange,
        ];

        return $this;
    }

    /**
     * @return array
     */
    public function getBatchData()
    {
        return self::$batchData;
    }

    /**
     * @return $this
     * @throws \Exception
     */
    public function processBatchData()
    {
        if (self::$useBatch === true && !is_null(self::$senderInstance)) {
            if (function_exists('exec')) {
                $this->saveDataRealtime($this->getBatchData());
            } else {
                self::$senderInstance->sendMessages($this->getBatchData());
            }
        }

        return $this;
    }

    /**
     * @return $this
     */
    public function useBatchData()
    {
        if (self::$useBatch !== true) {
            self::$useBatch = true;
            self::$batchData = [];
        }

        return $this;
    }
}
