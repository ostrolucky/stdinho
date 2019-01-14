<?php

declare(strict_types=1);

namespace Ostrolucky\Stdinho\Bufferer;

use Amp\ByteStream\ResourceInputStream;
use Amp\ByteStream\ResourceOutputStream;
use Amp\Coroutine;
use Amp\Deferred;
use Amp\Promise;
use Ostrolucky\Stdinho\ProgressBar;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Output\ConsoleSectionOutput;

class PipeBufferer implements BuffererInterface
{
    /**
     * @var LoggerInterface
     */
    private $logger;
    /**
     * @var ResourceInputStream
     */
    private $inputStream;
    /**
     * @var ResourceOutputStream
     */
    private $outputStream;

    /**
     * @var Deferred
     */
    private $mimeType;
    /**
     * @var string
     */
    private $filePath;
    /**
     * @var ProgressBar
     */
    private $progressBar;

    /**
     * @var bool
     */
    private $buffering = true;
    /**
     * @var Deferred|null
     */
    private $deferred;

    /**
     * @param resource $inputStream
     */
    public function __construct(
        LoggerInterface $logger,
        $inputStream,
        ?string $outputPath,
        ConsoleSectionOutput $output
    ) {
        $this->logger = $logger;
        $this->inputStream = new ResourceInputStream($inputStream);
        $this->outputStream = new ResourceOutputStream($fOutput = $outputPath ? fopen($outputPath, 'wb') : tmpfile());
        $this->mimeType = new Deferred();
        $this->filePath = $outputPath ?: stream_get_meta_data($fOutput)['uri'];
        $this->progressBar = new ProgressBar($output, 0, 'buffer');
    }

    public function __invoke(): Promise
    {
        $generator = function (): \Generator {
            $this->logger->debug("Saving stdin to $this->filePath");

            $bytesDownloaded = 0;
            while (null !== $chunk = yield $this->inputStream->read()) {
                yield $this->outputStream->write($chunk);

                if ($bytesDownloaded === 0) {
                    $mimeType = (new \finfo(FILEINFO_MIME))->buffer($chunk);
                    $this->logger->debug(sprintf('Stdin MIME type detected: "%s"', $mimeType));
                    $this->mimeType->resolve($mimeType);
                }

                $this->progressBar->setProgress($bytesDownloaded += strlen($chunk));
                $this->resolveDeferrer();
            }

            $this->buffering = false;
            $this->progressBar->finish();
            $this->logger->debug("Stdin transfer done, $bytesDownloaded bytes downloaded");
            $this->resolveDeferrer();
        };

        return new Coroutine($generator());
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
        return ($this->deferred = $this->deferred ?: new Deferred())->promise();
    }

    public function getCurrentProgress(): int
    {
        return $this->progressBar->step;
    }

    private function resolveDeferrer(): void
    {
        if (!$this->deferred) {
            return;
        }

        $deferrer = $this->deferred;
        $this->deferred = null;
        $deferrer->resolve();
    }
}
