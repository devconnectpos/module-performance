<?php

namespace SM\Performance\Command;

use Magento\Framework\App\Area;
use Magento\Framework\App\State;
use Magento\Framework\Exception\LocalizedException;
use SM\Performance\Helper\RealtimeManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class SendRealtime
 *
 * @package SM\Performance\Command
 */
class SendRealtime extends Command
{
    protected $appState;

    /**
     * @var \SM\Performance\Helper\RealtimeManager
     */
    private $realtimeManager;

    public function __construct(
        RealtimeManager $realtimeManager,
        State $appState,
        $name = null
    ) {
        $this->realtimeManager = $realtimeManager;
        $this->appState = $appState;
        parent::__construct($name);
    }

    protected function configure()
    {
        $this->setName("cpos:sendrealtime");
        $this->setDescription("Realtime sync command for PHP async task");
        $this->addArgument('data', InputArgument::REQUIRED, "json data to send server");
        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $logger = $objectManager->get('Psr\Log\LoggerInterface');

        try {
            $this->appState->emulateAreaCode(Area::AREA_ADMINHTML, function (InputInterface $input, OutputInterface $output){
                $data = $input->getArgument('data');
                if (is_string($data)) {
                    $data = json_decode((string)$data, true);
                    if (is_array($data)) {
                        $res = $this->realtimeManager->getSenderInstance()->sendMessages($data);
                        $output->writeln('<info>' .json_encode($res). '</info>');
                    } else {
                        $output->writeln('<error>data_wrong_format</error>');
                    }
                } else {
                    $output->writeln('<error>data_wrong_format</error>');
                }
            }, [$input, $output]);
        } catch (\Throwable $e) {
            $logger->info("====> [CPOS] Failed to send realtime: {$e->getMessage()}");
            $logger->info($e->getTraceAsString());
        }
    }
}
