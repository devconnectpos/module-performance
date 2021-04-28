<?php

namespace SM\Performance\Command;

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
        try {
            $appState->getAreaCode();
        } catch (LocalizedException $e) {
            $appState->setAreaCode('adminhtml');
        }
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
        $data = $input->getArgument('data');
        if (!is_null($data) && is_string($data)) {
            $data = json_decode($data, true);
            if (is_array($data)) {
                $res = $this->realtimeManager->getSenderInstance()->sendMessages($data);
                $output->writeln('<info>' . json_encode($res) . '</info>');
            } else {
                $output->writeln('<error>data_wrong_format</error>');
            }
        } else {
            $output->writeln('<error>data_wrong_format</error>');
        }
    }
}
