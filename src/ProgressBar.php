<?php

declare(strict_types=1);

namespace Ostrolucky\Stdinho;

use Symfony\Component\Console\Helper\Helper;
use Symfony\Component\Console\Helper\ProgressBar as SymfonyProgressBar;
use Symfony\Component\Console\Output\ConsoleSectionOutput;

SymfonyProgressBar::setFormatDefinition(
    'buffer',
    '[Stdin] Buffered: %current_volume% | %speed% | %elapsed_label%: %elapsed%'
);
SymfonyProgressBar::setFormatDefinition(
    'portal',
    '[%host%] Downloaded: %current_volume% | %speed% | %elapsed%/%estimated% |%aborted% %percent%%'
);
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
SymfonyProgressBar::setPlaceholderFormatterDefinition('host', function(SymfonyProgressBar $bar) {
    return $bar->host;
});
SymfonyProgressBar::setPlaceholderFormatterDefinition('aborted', function(SymfonyProgressBar $bar) {
    return empty($bar->aborted) ? '' : ' aborted at';
});

class ProgressBar
{
    /**
     * @var SymfonyProgressBar
     */
    private $wrappedProgressBar;

    public function __construct(ConsoleSectionOutput $output, int $max, string $format, ?string $host = null)
    {
        $this->wrappedProgressBar = new SymfonyProgressBar($output, $max);
        $this->wrappedProgressBar->setFormat($format);
        $this->wrappedProgressBar->host = $host;
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
        $this->wrappedProgressBar->aborted = true;
        $this->wrappedProgressBar->display();
    }
}
