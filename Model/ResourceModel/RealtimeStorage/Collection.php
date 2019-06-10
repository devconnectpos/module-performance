<?php
namespace SM\Performance\Model\ResourceModel\RealtimeStorage;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{
    protected function _construct()
    {
        $this->_init('SM\Performance\Model\RealtimeStorage', 'SM\Performance\Model\ResourceModel\RealtimeStorage');
    }
}
