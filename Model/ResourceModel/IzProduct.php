<?php
/**
 * Created by KhoiLe - mr.vjcspy@gmail.com
 * Date: 7/29/17
 * Time: 12:15 PM
 */

namespace SM\Performance\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\Context;
use Magento\Store\Model\StoreManagerInterface;
use SM\Integrate\Model\WarehouseIntegrateManagement;

class IzProduct extends AbstractProductCache
{

    /**
     * @var int | null
     */
    protected $warehouseId;
    /**
     * Store scope Id
     *
     * @var int
     */
    private $storeId;
    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    private $storeManager;

    /**
     * IzProduct constructor.
     *
     * @param \Magento\Framework\Model\ResourceModel\Db\Context $context
     * @param \Magento\Store\Model\StoreManagerInterface        $storeManager
     * @param null                                              $connectionName
     */
    public function __construct(
        Context $context,
        StoreManagerInterface $storeManager,
        $connectionName = null
    ) {
        $this->storeManager = $storeManager;
        parent::__construct($context, $connectionName);
    }

    /**
     * @return int|null
     */
    public function getWarehouseId()
    {
        return $this->warehouseId;
    }

    /**
     * @param $warehouseId
     *
     * @return $this
     */
    public function setWarehouseId($warehouseId)
    {
        if ($warehouseId === null) {
            $this->warehouseId = WarehouseIntegrateManagement::getWarehouseId();
        } else {
            $this->warehouseId = $warehouseId;
        }

        return $this;
    }

    /**
     * Set store for resource model
     *
     * @param null|string|bool|int $store
     *
     * @return $this
     */
    public function setStoreId($store)
    {
        if (is_int($store)) {
            $this->storeId = $store;
        } else {
            $this->storeId = $this->storeManager->getStore()->getId();
        }
        if (empty($this->storeId)) {
            $defaultStore = $this->storeManager->getDefaultStoreView();
            if ($defaultStore) {
                $this->storeId = (int)$defaultStore->getId();
            }
        }

        return $this;
    }

    /**
     * Retrieve store for resource model
     *
     * @return int
     */
    public function getStoreId()
    {
        return $this->storeId;
    }

    /**
     * Retrieve Flat Table name
     *
     * @param null $storeId
     * @param null $warehouseId
     *
     * @return string
     * @throws \Exception
     */
    public function getCacheTableName($storeId = null, $warehouseId = null)
    {
        if ($storeId === null) {
            $storeId = $this->getStoreId();
        }

        if ($warehouseId === null) {
            $warehouseId = $this->getWarehouseId();
        }

        if ($storeId === null) {
            throw new \Exception('Please define store id when create or update cache');
        }

        return $this->getTable('iz_product_' . $storeId . '_' . $warehouseId);
    }

    /**
     * Resource initialization
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init('iz_product_', 'id');
        $this->setStoreId(null);
        $this->setWarehouseId(null);
    }

    /**
     * Retrieve main resource table name
     *
     * @return string
     * @throws \Exception
     */
    public function getMainTable()
    {
        return $this->getCacheTableName($this->getStoreId(), $this->getWarehouseId());
    }
}
