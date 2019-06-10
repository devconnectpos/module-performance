<?php
/**
 * Created by PhpStorm.
 * User: kid
 * Date: 04/06/2018
 * Time: 15:27
 */
namespace SM\Performance\Cron;

use SM\Performance\Helper\RealtimeManager;
use SM\Performance\Model\RealtimeStorageFactory;
use SM\XRetail\Helper\Data;

class Realtime
{
    /**
     * @var \SM\Performance\Helper\RealtimeManager
     */
    private $realtimeManager;

    /**
     * @var \SM\Performance\Model\RealtimeStorageFactory
     */
    protected $realtimeStorageFactory;

    public function __construct(
        Data $dataHelper,
        RealtimeManager $realtimeManager,
        RealtimeStorageFactory $realtimeStorageFactory
    ) {
        $this->dataHelper           = $dataHelper;
        $this->realtimeManager      = $realtimeManager;
        $this->realtimeStorageFactory  = $realtimeStorageFactory;
    }

    /**
     * cronjob realtime storege
     *
     * @return void
     */

    public function execute()
    {
        $realtimeModel  = $this->realtimeStorageFactory->create();
        $collection     = $realtimeModel->getCollection();

        foreach ($collection->getItems() as $item) {
            $data = array();
            $dataRealtime   = json_decode($item['data_realtime']);
            foreach ($dataRealtime as $dt) {
                $data[]     = (array)$dt;
            }
            $this->realtimeManager->getSenderInstance()->sendMessages($data);

            //delete record
            $item->delete();
        }
    }
}
