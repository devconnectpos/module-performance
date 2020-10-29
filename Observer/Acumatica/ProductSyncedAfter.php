<?php
declare(strict_types=1);

namespace SM\Performance\Observer\Acumatica;

use Exception;
use Magento\Catalog\Model\Product\Type;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Catalog\Model\ProductFactory;
use SM\Performance\Helper\RealtimeManager;
use SM\Performance\Observer\ModelAfterSave;
use Magento\Catalog\Model\ResourceModel\Product as ProductModel;

/**
 * Class ProductSyncedAfter
 * @package SM\Performance\Observer\Acumatica
 */
class ProductSyncedAfter implements ObserverInterface
{
    /**
     * @var ProductFactory
     */
    protected $productFactory;
    
    /**
     * @var RealtimeManager
     */
    private $realtimeManager;
    
    /**
     * @var ModelAfterSave
     */
    private $modelAfterSave;
    
    /**
     * @var ProductModel
     */
    private $productModel;
    
    /**
     * ProductSyncedAfter constructor.
     * @param ProductFactory $productFactory
     * @param RealtimeManager $realtimeManager
     * @param ModelAfterSave $modelAfterSave
     * @param ProductModel $productModel
     */
    public function __construct(
        ProductFactory $productFactory,
        RealtimeManager $realtimeManager,
        ModelAfterSave $modelAfterSave,
        ProductModel $productModel
    ) {
        $this->productFactory = $productFactory;
        $this->realtimeManager = $realtimeManager;
        $this->modelAfterSave = $modelAfterSave;
        $this->productModel = $productModel;
    }
    
    /**
     * @param Observer $observer
     * @throws Exception
     */
    public function execute(Observer $observer)
    {
        $productData = $observer->getEvent()->getData('productData');
        if ($productData === null) {
            return;
        }
        
        $product = $this->productFactory->create()->load($productData->getProductId());
        $productId = $product->getId();
        if (!$productId) {
            return;
        }
        
        $ids = [];
        $ids[] = $productId;
        $productType = $product->getTypeId();
        
        if ($productType === 'configurable') {
            /** @var Configurable $instanceType */
            $instanceType = $product->getTypeInstance();
            $childIds     = $instanceType->getChildrenIds($productId);
            foreach ($childIds as $_ids) {
                $ids[] = $_ids;
            }
        }
        if ($productType === Type::TYPE_SIMPLE || $productType === Type::TYPE_VIRTUAL) {
            $ids = array_merge(
                $ids,
                $this->modelAfterSave->getBundleParentIds($productId),
                $this->modelAfterSave->getGroupedParentIds($productId),
                $this->modelAfterSave->getConfigurableParentIds($productId)
            );
        }
        
        $this->createSourceItem($ids);
        $idString = implode(',', array_unique($ids));
    
        $this->logActivity('Init Sync Products: ' . $idString);
        
        $this->realtimeManager->trigger(
            RealtimeManager::PRODUCT_ENTITY,
            $idString,
            RealtimeManager::TYPE_CHANGE_UPDATE
        );
    }
    
    /**
     * @param array $ids
     */
    public function createSourceItem($ids)
    {
        $connection = $this->productModel->getConnection();
        $inventorySourceTable = $this->productModel->getTable('inventory_source_item');
        
        foreach ($ids as $id) {
            $product = $this->productFactory->create()->load($id);
            $query = $connection->select()->from($inventorySourceTable)->where('sku = ?', $product->getSku());
            if ((int) $connection->fetchOne($query) !== 0) {
                continue;
            }
            
            $result = $connection->insert(
                $inventorySourceTable,
                ['source_code' => 'default', 'sku' => $product->getSku(), 'quantity' => 0, 'status' => 1]
            );
            if (!(bool)$result) {
                $this->logActivity('Unable to create inventory source for item ' . $product->getSku());
            }
        }
    }
    
    /**
     * @param string $message
     */
    public function logActivity($message)
    {
        $writer = new \Zend\Log\Writer\Stream(BP . '/var/log/SM/ProductAcumaticaSync.log');
        $logger = new \Zend\Log\Logger();
        $logger->addWriter($writer);
        $logger->info($message);
    }
}