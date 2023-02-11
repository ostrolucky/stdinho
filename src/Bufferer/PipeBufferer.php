<?php

declare(strict_types=1);

namespace Ostrolucky\Stdinho\Bufferer;

use Amp\ByteStream\ReadableStream;
use Amp\ByteStream\WritableResourceStream;
use Amp\ByteStream\WritableStream;
use Amp\DeferredFuture;
use Amp\Future;
use Amp\Socket\ServerSocket;
use Ostrolucky\Stdinho\ProgressBar;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Output\ConsoleSectionOutput;

class PipeBufferer extends AbstractBufferer
{
    private DeferredFuture $mimeType;
    private ProgressBar $progressBar;
    private bool $buffering = true;
    private ?DeferredFuture $deferred = null;

    public function __construct(
        ReadableStream $inputStream,
        ConsoleSectionOutput $output,
        private LoggerInterface $logger,
        private WritableStream $outputStream,
        private ServerSocket $server,
        private Future $promiseThatIsResolvedWhenSomebodyConnects,
        private int $bufferSize,
    ) {
        $this->inputStream = $inputStream;
        $this->mimeType = new DeferredFuture();
        $this->progressBar = new ProgressBar($output, 0, 'buffer');

        $filePath = '';

        if ($outputStream instanceof WritableResourceStream) {
            $filePath = stream_get_meta_data($outputStream->getResource())['uri'];
        }

        parent::__construct($filePath);
    }

    public function __invoke(): void
    {
        $this->logger->debug("Saving stdin to $this->filePath");

        while (null !== $chunk = $this->inputStream->read()) {
            $this->outputStream->write($chunk);

            if ($this->progressBar->getProgress() === 0) {
                $mimeType = (new \finfo(FILEINFO_MIME))->buffer($chunk);
                $this->logger->debug(sprintf('Stdin MIME type detected: "%s"', $mimeType));
                $this->mimeType->complete($mimeType);
            }

            $this->progressBar->advance(strlen($chunk));
            $this->resolveDeferrer();

            // This happens after write in order to give bufferer a chance to detect mimeType even if bufferSize is 0
            if ($this->progressBar->getProgress() < $this->bufferSize) {
                continue;
            }

            $this->logger->warning(
                'Max buffer size reached. Disabling buffering and falling back to piping stdin to socket directly. Only one client connection allowed.'
            );

            // Wait until at least one client connects. For first client it's safe to consume STDIN directly.
            $this->promiseThatIsResolvedWhenSomebodyConnects->await();
            $this->server->close();

            break;
        }

        $this->buffering = false;
        $this->progressBar->finish();
        $this->logger->debug("Buffering to file stopped, {$this->progressBar->getProgress()} bytes stored");
        $this->resolveDeferrer();
    }

    public function getMimeType(): Future
    {
        return $this->mimeType->getFuture();
    }

    public function isBuffering(): bool
    {
        return $this->buffering;
    }

    public function waitForWrite(): Future
    {
        return ($this->deferred = $this->deferred ?: new DeferredFuture())->getFuture();
    }

    public function getCurrentProgress(): int
    {
        return $this->progressBar->getProgress();
    }

    private function resolveDeferrer(): void
    {
        if (!$this->deferred) {
            return;
        }

        $deferrer = $this->deferred;
        $this->deferred = null;
        $deferrer->complete();
    }
}
