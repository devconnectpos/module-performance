<?php

namespace SM\Performance\Command;

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
            $this->state->getAreaCode();
        } catch (\Magento\Framework\Exception\LocalizedException $e) {
            $this->state->setAreaCode('adminhtml');
        }
        $this->cacheKeeper->generateProductCacheTables();

        $output->writeln('DONE');
        $timeElapsedSecs = microtime(true) - $start;
        $output->writeln('Execution time: ' . $timeElapsedSecs);
    }

}
