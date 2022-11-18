<?php
/**
 * Created by IntelliJ IDEA.
 * User: vjcspy
 * Date: 4/5/17
 * Time: 10:03 AM
 */

namespace SM\Performance\Gateway;

/**
 * Class Sender
 *
 * @package SM\Performance\Gateway
 */

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;
use SM\Performance\Helper\CacheKeeper;

/**
 * Class Sender
 *
 * @package SM\Performance\Gateway
 */
class Sender
{
    const REALTIME_URL_CONFIG_PATH = 'xpos/advance/realtime_url';
    const LICENSE_KEY_CONFIG_PATH = 'xpos/general/retail_license';

    public static $CLOUD_URL = "http://cloud.connectpos.com";
    //static $CLOUD_URL = "http://cloud.local:2005";
    //static $CLOUD_URL = "http://accounts.product.smartosc.com:2005";

    protected static $instance;

    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    private $storeManagement;
    /**
     * @var \Psr\Log\LoggerInterface
     */
    private $logger;
    /**
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    private $scopeConfig;
    /**
     * @var \Magento\Framework\Encryption\EncryptorInterface
     */
    private $encryptor;
    /**
     * @var string
     */
    protected $licenseKey;

    /**
     * @var string
     */
    protected $baseUrl;

    /**
     * @var \Magento\Framework\HTTP\Client\Curl
     */
    protected $curl;

    /**
     * @var \Magento\Framework\Serialize\Serializer\Json
     */
    protected $json;

    /**
     * RealtimeManager constructor.
     *
     * @param \Magento\Store\Model\StoreManagerInterface         $storeManagement
     * @param \Psr\Log\LoggerInterface                           $logger
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param \Magento\Framework\Encryption\EncryptorInterface   $encryptor
     * @param \Magento\Framework\HTTP\Client\Curl                $curl
     */
    public function __construct(
        StoreManagerInterface $storeManagement,
        LoggerInterface $logger,
        ScopeConfigInterface $scopeConfig,
        EncryptorInterface $encryptor,
        \Magento\Framework\HTTP\Client\Curl $curl,
        \Magento\Framework\Serialize\Serializer\Json $json
    ) {
        $this->encryptor = $encryptor;
        $this->scopeConfig = $scopeConfig;
        $this->logger = $logger;
        $this->storeManagement = $storeManagement;
        $this->curl = $curl;
        $this->json = $json;
    }

    /**
     * @param $entity
     * @param $entityId
     * @param $typeChange
     */
    public function sendMessage($entity, $entityId, $typeChange)
    {
        if (is_null($this->licenseKey)) {
            $this->licenseKey = $this->encryptor->decrypt($this->scopeConfig->getValue(self::LICENSE_KEY_CONFIG_PATH));
        }

        if (is_null($this->baseUrl)) {
            $this->baseUrl = $this->getRealtimeUrl();
        }

        if (!empty($this->licenseKey) && !empty($this->baseUrl)) {
            foreach ($this->baseUrl as $realtimeUrl) {
                $param = [
                    'license'    => $this->licenseKey,
                    'base_url'   => $realtimeUrl,
                    'data'       => [
                        'entity'      => $entity,
                        'entity_id'   => $entityId,
                        'type_change' => $typeChange,
                    ],
                    'cache_time' => intval(microtime(true) * 1000),
                    'created_at' => date("Y-m-d H:i:s"),
                ];
            }

            $this->sendPostViaSocket($this->getBaseUrl(), $param);
        }
    }

    /**
     * @param $data
     *
     * @return bool
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function sendMessages($data)
    {
        if (is_null($this->licenseKey)) {
            $this->licenseKey = $this->encryptor->decrypt($this->scopeConfig->getValue(self::LICENSE_KEY_CONFIG_PATH));
        }
        if (is_null($this->baseUrl)) {
            $this->baseUrl = $this->getRealtimeUrl();
        }
        $checkExisted = [];
        if (!empty($this->licenseKey) && !empty($this->baseUrl)) {
            $batch = [];

            foreach ($data as $datum) {
                $checkKey = $datum['entity'].'_'.$datum['entity_id'].'_'.$datum['type_change'];

                if (in_array($checkKey, $checkExisted, true)) {
                    continue;
                }

                foreach ($this->baseUrl as $realtimeUrl) {
                    $param = [
                        'license'    => $this->licenseKey,
                        'base_url'   => $realtimeUrl,
                        'data'       => [
                            'entity'      => $datum['entity'],
                            'entity_id'   => $datum['entity_id'],
                            'type_change' => $datum['type_change'],
                        ],
                        'cache_time' => CacheKeeper::getCacheTime(),
                        'created_at' => date("Y-m-d H:i:s"),
                    ];
                    $batch[] = $param;
                }

                $checkExisted[] = $checkKey;
            }

            return $this->sendPostViaSocket($this->getBaseUrl(), ["batch" => $batch]);
        }

        return false;
    }

    /**
     * @param $data
     *
     * @return string|null
     */
    public function getDataToSendViaRawSender($data)
    {
        if (is_null($this->licenseKey)) {
            $this->licenseKey = $this->encryptor->decrypt($this->scopeConfig->getValue(self::LICENSE_KEY_CONFIG_PATH));
        }
        if (is_null($this->baseUrl)) {
            $this->baseUrl = $this->getRealtimeUrl();
        }
        $checkExisted = [];
        if (!empty($this->licenseKey) && !empty($this->baseUrl)) {
            $batch = [];

            foreach ($data as $datum) {
                $checkKey = $datum['entity'].'_'.$datum['entity_id'].'_'.$datum['type_change'];

                if (in_array($checkKey, $checkExisted, true)) {
                    continue;
                }

                foreach ($this->baseUrl as $realtimeUrl) {
                    $param = [
                        'license'    => $this->licenseKey,
                        'base_url'   => $realtimeUrl,
                        'data'       => [
                            'entity'      => $datum['entity'],
                            'entity_id'   => $datum['entity_id'],
                            'type_change' => $datum['type_change'],
                        ],
                        'cache_time' => CacheKeeper::getCacheTime(),
                        'created_at' => date("Y-m-d H:i:s"),
                    ];
                    $batch[] = $param;
                }

                $checkExisted[] = $checkKey;
            }

            return $this->getBaseUrl().' '.$this->licenseKey.' '.$this->json->serialize(["batch" => $batch]);
        }

        return null;
    }

    /**
     * @param $url
     * @param $params
     *
     * @return mixed
     */
    public function sendPostViaSocket($url, $params)
    {
        try {
            $content = $this->json->serialize($params);

            $this->curl->setOption(CURLOPT_HEADER, false);
            $this->curl->setOption(CURLOPT_RETURNTRANSFER, true);
            $this->curl->setOption(CURLOPT_POST, true);
            $this->curl->setOption(CURLOPT_POSTFIELDS, $content);
            $this->curl->addHeader("Content-Type", "application/json");

            $this->curl->post($url, $params);
            $jsonResponse = $this->curl->getBody();

            $status = $this->curl->getStatus();

            if ($status !== 200) {
                $this->logger->debug("====> [CPOS] Error: Call to URL $url failed with status $status, response $jsonResponse");
            }

            return $this->json->unserialize($jsonResponse);
        } catch (\Throwable $e) {
            $this->logger->debug("====> [CPOS] Error sending message to socket: {$e->getMessage()}");
        }

        return [];
    }

    protected function getBaseUrl()
    {
        return self::$CLOUD_URL."/methods/client.trigger_realtime";
        //return "http://localhost:2005/methods/client.trigger_realtime";
        //return "http://xcloud.smartosc.com:2005/methods/client.trigger_realtime";
    }

    /**
     * @return mixed
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getRealtimeUrl()
    {
        // Support for multiple realtime URLs
        $realtimeUrls = $this->scopeConfig->getValue(self::REALTIME_URL_CONFIG_PATH);

        if (!empty($realtimeUrls)) {
            $splitUrls = explode(",", (string)$realtimeUrls);
            $results = [];

            foreach ($splitUrls as $url) {
                if (filter_var($url, FILTER_VALIDATE_URL) === false) {
                    continue;
                }
                $results[] = $url;
            }

            return $results;
        }

        return [$this->storeManagement->getStore()->getBaseUrl(UrlInterface::URL_TYPE_LINK, true)];
    }
}
