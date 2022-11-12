<?php

declare(strict_types=1);

namespace Ostrolucky\Stdinho;

use Psr\Log\AbstractLogger;
use Symfony\Component\Console\Output\OutputInterface;

class ConsoleLogger extends AbstractLogger
{
    public function __construct(private OutputInterface $output)
    {
    }

    public function log($level, string|\Stringable $message, array $context = []): void
    {
        $this->output->writeln(
            sprintf('%s <comment>[%s]</comment> <fg=white>%s</>', date('H:i:s'), $level, $message),
            OutputInterface::VERBOSITY_VERBOSE
        );
    }
}
