<?php
namespace SM\Performance\Model\ResourceModel\ProductCacheInstance;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{
    protected function _construct()
    {
        $this->_init(
            'SM\Performance\Model\ProductCacheInstance',
            'SM\Performance\Model\ResourceModel\ProductCacheInstance'
        );
    }
}
