<?php

namespace Ostrolucky\StdinFileServer;

use Psr\Log\AbstractLogger;
use Symfony\Component\Console\Output\OutputInterface;

class ConsoleLogger extends AbstractLogger
{
    /**
     * @var OutputInterface
     */
    private $output;

    public function __construct(OutputInterface $output)
    {
        $this->output = $output;
    }

    public function log($level, $message, array $context = [])
    {
        $this->output->writeln(
            sprintf('%s <comment>[%s]</comment> <fg=white>%s</>', date('H:i:s'), $level, $message),
            OutputInterface::VERBOSITY_VERBOSE
        );
    }
}