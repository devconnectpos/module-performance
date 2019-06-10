<?php

namespace SM\Performance\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class ProductCacheInstance extends AbstractDb
{
    protected function _construct()
    {
        $this->_init('sm_performance_product_cache_instance', 'id');
    }
}
