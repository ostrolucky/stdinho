<?php

namespace Ostrolucky\Stdinho;

use Symfony\Component\Console\Helper\Helper;
use Symfony\Component\Console\Terminal;

class ProgressBar
{
    private $format;
    private $placeholders;
    private $lastWriteTime;
    private $output;
    public $step = 0;
    public $max;
    private $startTime;
    private $percent = 0.0;
    private $terminal;

    private $firstRun = true;

    public function __construct(ConsoleSectionOutput $output, int $max, string $format, string $host = null)
    {
        $this->output = $output;
        $this->max = $max;
        $this->format = [
            'buffer' => '[Stdin] Buffered: %current_volume% | %speed% | %elapsed_label%: %elapsed%',
            'portal' => '[%host%] Downloaded: %current_volume% | %speed% | %elapsed%/%estimated% | %percent%%',
        ][$format];
        $this->placeholders = [
            'elapsed' => function (ProgressBar $bar) {
                return Helper::formatTime(time() - $bar->startTime);
            },
            'estimated' => function (ProgressBar $bar) {
                $progress = $bar->step;

                return Helper::formatTime($progress ? round((time() - $bar->startTime) / $progress * $bar->max) : 0);
            },
            'current' => function (ProgressBar $bar) {
                return str_pad($bar->step, strlen($bar->max), ' ', STR_PAD_LEFT);
            },
            'percent' => function (ProgressBar $bar) {
                return floor($bar->percent * 100);
            },
            'current_volume' => function (ProgressBar $bar) {
                return Helper::formatMemory($bar->step);
            },
            'elapsed_label' => function (ProgressBar $bar) {
                return $bar->max >= $bar->step ? '<info>Finished</info> in' : 'Elapsed';
            },
            'speed' => function (ProgressBar $bar) {
                return Helper::formatMemory($bar->step / max(1, time() - $bar->startTime)).'/s';
            },
            'max_volume' => function (ProgressBar $bar) {
                return Helper::formatMemory($bar->max);
            },
            'host' => function () use ($host) {
                return $host;
            },
        ];
        $this->terminal = new Terminal();
        $this->startTime = time();
    }

    /**
     * Advances the progress output X steps.
     *
     * @param int $step Number of steps to advance
     */
    public function advance(int $step)
    {
        $this->setProgress($this->step + $step);
    }

    public function setProgress(int $step)
    {
        $this->step = $step;
        $this->percent = $this->max ? (float)$step / $this->max : 0;

        if (microtime(true) - $this->lastWriteTime >= .1) {
            $this->overwrite($this->buildLine());
        }
    }

    /**
     * Finishes the progress output.
     */
    public function finish(): void
    {
        if ($this->step > $this->max) {
            $this->max = $this->step;
        }

        $this->overwrite($this->buildLine());
    }

    /**
     * Overwrites a previous message to the output.
     */
    private function overwrite(string $message): void
    {
        if ( ! $this->firstRun) {
            $this->output->clear(1);
        }

        $this->firstRun = false;
        $this->lastWriteTime = microtime(true);
        $this->output->write($message);
    }

    private function buildLine(): string
    {
        $regex = "{%([a-z\-_]+)(?:\:([^%]+))?%}i";
        $callback = function ($matches) {
            $text = call_user_func($this->placeholders[$matches[1]], $this, $this->output);

            if (isset($matches[2])) {
                $text = sprintf('%'.$matches[2], $text);
            }

            return $text;
        };
        $line = preg_replace_callback($regex, $callback, $this->format);

        // gets string length for each sub line with multiline format
        $linesLength = array_map(
            function ($subLine) {
                return Helper::strlenWithoutDecoration($this->output->getFormatter(), rtrim($subLine, "\r"));
            }, explode("\n", $line)
        );

        $linesWidth = max($linesLength);

        $terminalWidth = $this->terminal->getWidth();
        if ($linesWidth <= $terminalWidth) {
            return $line;
        }

        return preg_replace_callback($regex, $callback, $this->format);
    }
}
