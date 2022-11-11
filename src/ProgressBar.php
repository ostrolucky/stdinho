<?php

declare(strict_types=1);

namespace Ostrolucky\Stdinho;

use Symfony\Component\Console\Helper\Helper;
use Symfony\Component\Console\Helper\ProgressBar as SymfonyProgressBar;
use Symfony\Component\Console\Output\ConsoleSectionOutput;

SymfonyProgressBar::setPlaceholderFormatterDefinition('current_volume', function(SymfonyProgressBar $bar) {
    return Helper::formatMemory($bar->getProgress());
});
SymfonyProgressBar::setPlaceholderFormatterDefinition('elapsed_label', function(SymfonyProgressBar $bar) {
    return $bar->getMaxSteps() >= $bar->getProgress() ? '<info>Finished</info> in' : 'Elapsed';
});
SymfonyProgressBar::setPlaceholderFormatterDefinition('speed', function(SymfonyProgressBar $bar) {
    return Helper::formatMemory((int)($bar->getProgress() / max(1, time() - $bar->getStartTime()))).'/s';
});
SymfonyProgressBar::setPlaceholderFormatterDefinition('max_volume', function(SymfonyProgressBar $bar) {
    return Helper::formatMemory($bar->getMaxSteps());
});

class ProgressBar
{
    private SymfonyProgressBar $wrappedProgressBar;
    private bool $aborted = false;

    public function __construct(
        ConsoleSectionOutput $output,
        int $max,
        private string $format,
        private ?string $host = null)
    {
        $this->wrappedProgressBar = new SymfonyProgressBar($output, $max);
        $this->updateFormat();
    }

    public function getProgress(): int
    {
        return $this->wrappedProgressBar->getProgress();
    }

    public function advance(int $step): void
    {
        $this->wrappedProgressBar->advance($step);
    }

    public function setMaxSteps(int $max): void
    {
        $this->wrappedProgressBar->setMaxSteps($max);
    }

    public function finish(): void
    {
        $this->wrappedProgressBar->finish();
    }

    public function abort(): void
    {
        $this->aborted = true;
        $this->updateFormat();
        $this->wrappedProgressBar->display();
    }

    private function updateFormat(): void
    {
        $this->wrappedProgressBar->setFormat(match($this->format) {
            'buffer' => '[Stdin] Buffered: %current_volume% | %speed% | %elapsed_label%: %elapsed%',
            'portal' => '[' . $this->host . '] Downloaded: %current_volume% | %speed% | %elapsed%/%estimated% |' . ($this->aborted ? ' aborted at' : '') . ' %percent%%',
        });
    }
}
