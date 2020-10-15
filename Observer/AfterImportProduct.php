<?php
declare(strict_types=1);

namespace SM\Performance\Observer;

use Exception;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use SM\Performance\Helper\RealtimeManager;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;

/**
 * Class AfterImportProduct
 * @package SM\Performance\Observer
 */
class AfterImportProduct implements ObserverInterface
{
    /**
     * @var RealtimeManager
     */
    private $realtimeManager;
    
    /**
     * @var ProductCollectionFactory
     */
    private $productCollection;
    
    /**
     * AfterImportProduct constructor.
     * @param RealtimeManager $realtimeManager
     * @param ProductCollectionFactory $productCollection
     */
    public function __construct(
        RealtimeManager $realtimeManager,
        ProductCollectionFactory $productCollection
    ) {
        $this->realtimeManager = $realtimeManager;
        $this->productCollection = $productCollection;
    }
    
    /**
     * @param Observer $observer
     * @throws Exception
     */
    public function execute(Observer $observer)
    {
        $importedSkus = [];
        $bunches = $observer->getDataByKey('bunch');
        foreach ($bunches as $bunch) {
            if (isset($bunch['sku'])) {
                $importedSkus[] = trim($bunch['sku']);
            }
        }
        
        if (empty($importedSkus)) {
            return;
        }
        
        $products = $this->productCollection->create();
        $productIds = $products->addFieldToFilter('sku', ['in' => $importedSkus])->getAllIds();
        if (empty($productIds)) {
            return;
        }
        
        $this->realtimeManager->trigger(
            RealtimeManager::PRODUCT_ENTITY,
            implode(',', $productIds),
            RealtimeManager::TYPE_CHANGE_UPDATE
        );
    }
}