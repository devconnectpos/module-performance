<?php
namespace SM\Performance\Model;

use Magento\Framework\DataObject\IdentityInterface;
use Magento\Framework\Model\AbstractModel;
use SM\Performance\Api\Data\ProductCacheInstanceInterface;

class ProductCacheInstance extends AbstractModel implements ProductCacheInstanceInterface, IdentityInterface
{
    const CACHE_TAG = 'sm_performance_productcacheinstance';

    protected function _construct()
    {
        $this->_init('SM\Performance\Model\ResourceModel\ProductCacheInstance');
    }

    public function getIdentities()
    {
        return [self::CACHE_TAG . '_' . $this->getId()];
    }
}
