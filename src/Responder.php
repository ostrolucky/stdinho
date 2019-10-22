<?php

declare(strict_types=1);

namespace Ostrolucky\Stdinho;

use Amp\ByteStream\InputStream;
use Amp\ByteStream\StreamException;
use Amp\Deferred;
use Amp\Socket\Socket;
use Error;
use Ostrolucky\Stdinho\Bufferer\AbstractBufferer;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Output\ConsoleOutput;

class Responder
{
    /**
     * @var LoggerInterface
     */
    private $logger;
    /**
     * @var AbstractBufferer
     */
    private $bufferer;
    /**
     * @var ConsoleOutput
     */
    private $consoleOutput;
    /**
     * @var string[]
     */
    private $customHttpHeaders = [];
    /**
     * @var InputStream
     */
    private $inputStream;
    /**
     * @var Deferred
     */
    private $defererThatIsResolvedWhenSomebodyConnects;

    /**
     * @param string[] $customHttpHeaders
     */
    public function __construct(
        LoggerInterface $logger,
        AbstractBufferer $bufferer,
        ConsoleOutput $consoleOutput,
        array $customHttpHeaders,
        InputStream $inputStream,
        Deferred $defererThatIsResolvedWhenSomebodyConnects
    ) {
        $this->logger = $logger;
        $this->bufferer = $bufferer;
        $this->consoleOutput = $consoleOutput;
        $this->customHttpHeaders = $customHttpHeaders;
        $this->inputStream = $inputStream;
        $this->defererThatIsResolvedWhenSomebodyConnects = $defererThatIsResolvedWhenSomebodyConnects;
    }

    public function __invoke(Socket $socket): \Generator
    {
        $remoteAddress = (string)$socket->getRemoteAddress();
        $this->logger->debug("Accepted connection from $remoteAddress:\n".trim(yield $socket->read()));

        $header = [
            'HTTP/1.1 200 OK',
            'Content-Disposition: inline; filename="'.basename($this->bufferer->filePath).'"',
            'Content-Type:'.yield $this->bufferer->getMimeType(),
            'Connection: close',
        ];

        if (!$this->bufferer->isBuffering()) {
            $header[] = "Content-Length: {$this->bufferer->getCurrentProgress()}";
        }

        // Only at this point it's safe to resolve the promise, otherwise Responder
        // would think everything was buffered and specify wrong content length!
        try {
            $this->defererThatIsResolvedWhenSomebodyConnects->resolve();
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
            yield $socket->write(implode("\r\n", array_merge($header, $this->customHttpHeaders))."\r\n\r\n");
            yield from $this->write($socket, $progressBar);
            $progressBar->finish();
            $this->logger->debug("$remoteAddress finished download");
        } catch (StreamException $exception) {
            $this->logger->debug("$remoteAddress aborted download");
        }

        $socket->end();
    }

    private function write(Socket $socket, ProgressBar $progressBar): \Generator
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
                yield $this->bufferer->waitForWrite();

                continue;
            }

            if (null !== $chunk = yield $this->inputStream->read()) {
                yield $socket->write($chunk);

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
