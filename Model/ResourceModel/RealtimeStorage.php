<?php
namespace SM\Performance\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class RealtimeStorage extends AbstractDb
{
    protected function _construct()
    {
        $this->_init('sm_realtime_storage', 'id');
    }
}
