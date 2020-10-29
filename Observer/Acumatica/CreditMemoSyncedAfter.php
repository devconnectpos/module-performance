<?php
declare(strict_types=1);

namespace SM\Performance\Observer\Acumatica;

use Exception;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use SM\Performance\Observer\ModelAfterSave;
use Magento\Sales\Model\ResourceModel\Order\Creditmemo\CollectionFactory as CreditMemoCollection;
use Magento\Sales\Model\Order\Creditmemo;

/**
 * Class CreditMemoSyncedAfter
 * @package SM\Performance\Observer\Acumatica
 */
class CreditMemoSyncedAfter implements ObserverInterface
{
    /**
     * @var ModelAfterSave
     */
    private $modelAfterSave;
    
    /**
     * @var CreditMemoCollection
     */
    private $creditMemoFactory;
    
    /**
     * CreditMemoSyncedAfter constructor.
     * @param ModelAfterSave $modelAfterSave
     * @param CreditMemoCollection $creditMemoFactory
     */
    public function __construct(
        ModelAfterSave $modelAfterSave,
        CreditMemoCollection $creditMemoFactory
    ) {
        $this->modelAfterSave = $modelAfterSave;
        $this->creditMemoFactory = $creditMemoFactory;
    }
    
    /**
     * @param Observer $observer
     * @throws Exception
     */
    public function execute(Observer $observer)
    {
        $memoData = $observer->getEvent()->getData('creditMemoData');
        if ($memoData === null) {
            return;
        }
        
        $memoId = $memoData->getCreditMemoId();
        /** @var Creditmemo $creditMemo */
        $creditMemo = $this->creditMemoFactory->create()->addFieldToFilter('entity_id', $memoId)->getFirstItem();
        if (!$creditMemo->getId()) {
            return;
        }
    
        $this->modelAfterSave->updateOrderRetailStatus($creditMemo->getOrder());
    }
}