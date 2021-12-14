<?php

namespace SM\Performance\Controller\Adminhtml\Index;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface as HttpPostActionInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Ui\Component\MassAction\Filter;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use SM\Performance\Helper\RealtimeManager;

class MassSync extends Action implements HttpPostActionInterface
{

    /**
     * @var Filter
     */
    protected $filter;

    /**
     * @var CollectionFactory
     */
    protected $prodCollFactory;

    /**
     * @var \SM\Performance\Helper\RealtimeManager
     */
    protected $realtimeManager;

    /**
     * @param Context           $context
     * @param Filter            $filter
     * @param CollectionFactory $prodCollFactory
     * @param RealtimeManager   $realtimeManager
     */
    public function __construct(
        Context $context,
        Filter $filter,
        CollectionFactory $prodCollFactory,
        RealtimeManager $realtimeManager
    ) {
        $this->filter = $filter;
        $this->prodCollFactory = $prodCollFactory;
        $this->realtimeManager = $realtimeManager;
        parent::__construct($context);
    }

    /**
     * Execute action
     *
     * @return \Magento\Backend\Model\View\Result\Redirect
     * @throws \Magento\Framework\Exception\LocalizedException | \Exception
     */
    public function execute()
    {
        $collection = $this->filter->getCollection($this->prodCollFactory->create());

        foreach ($collection->getAllIds() as $productId) {
            $this->realtimeManager->triggerForce(
                RealtimeManager::PRODUCT_ENTITY,
                $productId,
                RealtimeManager::TYPE_CHANGE_UPDATE
            );
        }

        $this->messageManager->addSuccess(__('A total of %1 record(s) have been synced to ConnectPOS.', $collection->getSize()));
        /** @var \Magento\Backend\Model\View\Result\Redirect $resultRedirect */
        $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);

        return $resultRedirect->setPath('catalog/product/index');
    }
}
