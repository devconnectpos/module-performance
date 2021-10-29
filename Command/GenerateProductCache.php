<?php

namespace SM\Performance\Command;

use Magento\Framework\App\Area;
use Magento\Framework\App\State;
use SM\Performance\Helper\CacheKeeper;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class GenerateProductCache extends Command
{
    /**
     * @var CacheKeeper
     */
    protected $cacheKeeper;
    /**
     * @var State
     */
    protected $state;

    public function __construct(
        CacheKeeper $cacheKeeper,
        State $state,
        $name = null
    ) {
        parent::__construct($name);
        $this->cacheKeeper = $cacheKeeper;
        $this->state = $state;
    }

    public function configure()
    {
        $this->setName("cpos:generate-product-cache");
        $this->setDescription("Manually generate product cache tables for ConnectPOS");
        parent::configure();
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        //calculating execution time
        $start = microtime(true);
        try {
            $this->state->emulateAreaCode(
                Area::AREA_ADMINHTML, function (OutputInterface $output, $start) {
                $this->cacheKeeper->generateProductCacheTables();
                $output->writeln('DONE');
                $timeElapsedSecs = microtime(true) - $start;
                $output->writeln('Execution time: '.$timeElapsedSecs);
            }, [$output, $start]
            );
        } catch (\Throwable $e) {
            $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
            $logger = $objectManager->get('Psr\Log\LoggerInterface');
            $logger->critical('====> Failed to generate product cache');
            $logger->critical($e->getMessage()."\n".$e->getTraceAsString());
        }
    }

}
