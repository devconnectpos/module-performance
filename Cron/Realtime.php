<?php
/**
 * Created by PhpStorm.
 * User: kid
 * Date: 04/06/2018
 * Time: 15:27
 */

namespace SM\Performance\Cron;

use SM\Performance\Helper\RealtimeManager;
use Magento\Framework\Serialize\Serializer\Json;
use SM\Performance\Model\ResourceModel\RealtimeStorage\CollectionFactory as RealtimeCollectionFactory;

class Realtime
{
    /**
     * @var \SM\Performance\Helper\RealtimeManager
     */
    protected $realtimeManager;

    /**
     * @var RealtimeCollectionFactory
     */
    protected $realtimeCollectionFactory;

    /**
     * @var Json
     */
    protected $jsonSerializer;

    public function __construct(
        RealtimeManager $realtimeManager,
        RealtimeCollectionFactory $realtimeCollectionFactory,
        Json $jsonSerializer
    ) {
        $this->realtimeManager = $realtimeManager;
        $this->realtimeCollectionFactory = $realtimeCollectionFactory;
        $this->jsonSerializer = $jsonSerializer;
    }

    /**
     * cronjob realtime storege
     *
     * @return void
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function execute()
    {
        $collection = $this->realtimeCollectionFactory->create();

        foreach ($collection->getItems() as $item) {
            $data = array();
            $dataRealtime = $this->jsonSerializer->unserialize($item['data_realtime']);

            foreach ($dataRealtime as $dt) {
                $data[] = (array)$dt;
            }

            $this->realtimeManager->getSenderInstance()->sendMessages($data);

            //delete record
            $item->delete();

            // Sleep for 1 second before sending the next message
            usleep(1000000);
        }
    }
}
