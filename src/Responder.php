<?php

declare(strict_types=1);

namespace Ostrolucky\Stdinho;

use Amp\ByteStream\ReadableStream;
use Amp\ByteStream\StreamException;
use Amp\DeferredFuture;
use Amp\Socket\Socket;
use Error;
use Ostrolucky\Stdinho\Bufferer\AbstractBufferer;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Output\ConsoleOutput;

class Responder
{
    /**
     * @param string[] $customHttpHeaders
     */
    public function __construct(
        private LoggerInterface $logger,
        private AbstractBufferer $bufferer,
        private ConsoleOutput $consoleOutput,
        private array $customHttpHeaders,
        private ReadableStream $inputStream,
        private DeferredFuture $defererThatIsResolvedWhenSomebodyConnects
    ) {
    }

    public function __invoke(Socket $socket): void
    {
        $remoteAddress = (string)$socket->getRemoteAddress();
        $this->logger->debug("Accepted connection from $remoteAddress:\n".trim($socket->read()));

        $header = [
            'HTTP/1.1 200 OK',
            'Content-Disposition: inline; filename="'.basename($this->bufferer->filePath).'"',
            'Content-Type:'.$this->bufferer->getMimeType()->await(),
            'Connection: close',
        ];

        if (!$this->bufferer->isBuffering()) {
            $header[] = "Content-Length: {$this->bufferer->getCurrentProgress()}";
        }

        // Only at this point it's safe to resolve the promise, otherwise Responder
        // would think everything was buffered and specify wrong content length!
        try {
            $this->defererThatIsResolvedWhenSomebodyConnects->complete();
        } catch (Error $e) {
            // Promise might have been already resolved
        }

        $progressBar = new ProgressBar(
            $this->consoleOutput->section(),
            $this->bufferer->getCurrentProgress(),
            'portal',
            $remoteAddress
        );

        try {
            $socket->write(implode("\r\n", array_merge($header, $this->customHttpHeaders))."\r\n\r\n");
            $this->write($socket, $progressBar);
            $progressBar->finish();
            $this->logger->debug("$remoteAddress finished download");
        } catch (StreamException $exception) {
            $this->logger->debug("$remoteAddress aborted download");
            $progressBar->abort();
        }

        $socket->end();
    }

    private function write(Socket $socket, ProgressBar $progressBar): void
    {
        while (true) {
            $buffererProgress = $this->bufferer->getCurrentProgress();

            /**
             * Not trying to read the buffer when connected client caught up to it is important for following:
             *
             * 1. Reduce CPU usage and potential block operations thanks to avoiding executing logic in read()
             * 2. Prevent ResourceInputStream to close the resource when it detects feof. This serves as workaround.
             *
             * @see https://github.com/ostrolucky/stdinho/pull/2
             * @see https://github.com/amphp/byte-stream/issues/47
             */
            if ($buffererProgress <= $progressBar->getProgress() && $this->bufferer->isBuffering()) {
                $this->bufferer->waitForWrite()->await();

                continue;
            }

            if (null !== $chunk = $this->inputStream->read()) {
                $socket->write($chunk);

                $progressBar->setMaxSteps($this->bufferer->getCurrentProgress());
                $progressBar->advance(strlen($chunk));

                continue;
            }

            if (!$this->bufferer->inputStream) {
                break; // All input sources depleted -> finish download
            }

            // Use fallback inputStream - handy when PipeBufferer exits sooner than it consumes its inputStream
            $this->inputStream = $this->bufferer->inputStream;
            // Prevent other potential Responders to consume same inputStream. This can be solved in future
            $this->bufferer->inputStream = null;
        }
    }
}
