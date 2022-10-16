<?php

declare(strict_types=1);

namespace Ostrolucky\Stdinho\Bufferer;

use Amp\ByteStream\InputStream;
use Amp\ByteStream\OutputStream;
use Amp\ByteStream\ResourceOutputStream;
use Amp\Coroutine;
use Amp\Deferred;
use Amp\Promise;
use Amp\Socket\Server;
use Ostrolucky\Stdinho\ProgressBar;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Output\ConsoleSectionOutput;

class PipeBufferer extends AbstractBufferer
{
    private Deferred $mimeType;
    private ProgressBar $progressBar;
    private bool $buffering = true;
    private ?Deferred $deferred = null;

    public function __construct(
        InputStream $inputStream,
        ConsoleSectionOutput $output,
        private LoggerInterface $logger,
        private OutputStream $outputStream,
        private Server $server,
        private Promise $promiseThatIsResolvedWhenSomebodyConnects,
        private int $bufferSize,
    ) {
        $this->inputStream = $inputStream;
        $this->mimeType = new Deferred();
        $this->progressBar = new ProgressBar($output, 0, 'buffer');

        $filePath = '';

        if ($outputStream instanceof ResourceOutputStream) {
            $filePath = stream_get_meta_data($outputStream->getResource())['uri'];
        }

        parent::__construct($filePath);
    }

    public function __invoke(): Promise
    {
        $generator = function(): \Generator {
            $this->logger->debug("Saving stdin to $this->filePath");

            while (null !== $chunk = yield $this->inputStream->read()) {
                yield $this->outputStream->write($chunk);

                if ($this->progressBar->getProgress() === 0) {
                    $mimeType = (new \finfo(FILEINFO_MIME))->buffer($chunk);
                    $this->logger->debug(sprintf('Stdin MIME type detected: "%s"', $mimeType));
                    $this->mimeType->resolve($mimeType);
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
                yield $this->promiseThatIsResolvedWhenSomebodyConnects;
                $this->server->close();

                break;
            }

            $this->buffering = false;
            $this->progressBar->finish();
            $this->logger->debug("Buffering to file stopped, {$this->progressBar->getProgress()} bytes stored");
            $this->resolveDeferrer();
        };

        return new Coroutine($generator());
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
        return $this->progressBar->getProgress();
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
