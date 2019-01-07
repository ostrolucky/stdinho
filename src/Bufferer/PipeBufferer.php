<?php

namespace Ostrolucky\Stdinho\Bufferer;

use Amp\ByteStream\InputStream;
use Amp\Deferred;
use Amp\Promise;
use Ostrolucky\Stdinho\ProgressBar;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Output\ConsoleSectionOutput;

class PipeBufferer implements BuffererInterface
{
    private $logger;
    private $inputStream;

    private $mimeType;
    private $filePath;
    private $progressBar;

    private $buffering = true;

    private $deferred;

    /**
     * @param resource $inputStream
     */
    public function __construct(
        LoggerInterface $logger,
        InputStream $inputStream,
        ?string $outputPath,
        ConsoleSectionOutput $output
    ) {
        $this->logger = $logger;
        $this->inputStream = $inputStream;
        $this->mimeType = new \Amp\Deferred;
        $this->filePath = $outputPath ?? \tempnam(\sys_get_temp_dir(), 'stdinho') . '.tmp';
        $this->progressBar = new ProgressBar($output, 0, 'buffer');
    }

    public function __invoke(): \Generator
    {
        $this->logger->debug("Saving stdin to $this->filePath");

        $outputStream = yield \Amp\File\open($this->filePath, 'wb');

        $bytesDownloaded = 0;
        while (null !== $chunk = yield $this->inputStream->read()) {
            yield $promise = $outputStream->write($chunk);

            if ($this->deferred) {
                $deferred = $this->deferred;
                $this->deferred = null;
                $deferred->resolve($promise);
            }

            if ($bytesDownloaded === 0) {
                $mimeType = (new \finfo(FILEINFO_MIME))->buffer($chunk);
                $this->logger->debug(sprintf('Stdin MIME type detected: "%s"', $mimeType));
                $this->mimeType->resolve($mimeType);
            }

            $this->progressBar->setProgress($bytesDownloaded += strlen($chunk));
        }

        if ($this->deferred) {
            $deferred = $this->deferred;
            $this->deferred = null;
            $deferred->resolve(0);
        }

        $this->buffering = false;
        $this->progressBar->finish();
        $this->logger->debug("Stdin transfer done, $bytesDownloaded bytes downloaded");
    }

    public function getFilePath(): string
    {
        return $this->filePath;
    }

    public function getMimeType(): Promise
    {
        return $this->mimeType->promise();
    }

    public function isBuffering(): bool
    {
        return $this->buffering;
    }

    public function waitForWrite(): Promise
    {
        if (!$this->deferred) {
            $this->deferred = new Deferred;
        }

        return $this->deferred->promise();
    }

    public function getCurrentProgress(): int
    {
        return $this->progressBar->step;
    }
}