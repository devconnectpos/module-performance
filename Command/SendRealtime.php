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
        $writer = new \Zend\Log\Writer\Stream(BP . '/var/log/connectpos_realtime.log');
        $logger = new \Zend\Log\Logger();
        $logger->addWriter($writer);

        try {
            $randomSeconds = random_int(0, 5);
            if ($randomSeconds > 0) {
                $output->writeln("<info>Delayed realtime execution by {$randomSeconds} seconds</info>");
                sleep($randomSeconds);
            }
            $this->appState->emulateAreaCode(Area::AREA_ADMINHTML, function (InputInterface $input, OutputInterface $output){
                $data = $input->getArgument('data');
                if (is_string($data)) {
                    $data = json_decode($data, true, 512);
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
            $logger->info('====> Failed to send realtime');
            $logger->info($e->getMessage() . "\n" . $e->getTraceAsString());
        }
    }
}
