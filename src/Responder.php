<?php

declare(strict_types=1);

namespace Ostrolucky\Stdinho;

use Amp\ByteStream\InputStream;
use Amp\ByteStream\StreamException;
use Amp\Socket\Socket;
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
     * @param string[] $customHttpHeaders
     */
    public function __construct(
        LoggerInterface $logger,
        AbstractBufferer $bufferer,
        ConsoleOutput $consoleOutput,
        array $customHttpHeaders,
        InputStream $inputStream
    ) {
        $this->logger = $logger;
        $this->bufferer = $bufferer;
        $this->consoleOutput = $consoleOutput;
        $this->customHttpHeaders = $customHttpHeaders;
        $this->inputStream = $inputStream;
    }

    public function __invoke(Socket $socket): \Generator
    {
        $remoteAddress = $socket->getRemoteAddress();
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

        $progressBar = new ProgressBar(
            $this->consoleOutput->section(),
            $this->bufferer->getCurrentProgress(),
            'portal',
            $remoteAddress
        );

        try {
            yield $socket->write(implode("\r\n", array_merge($header, $this->customHttpHeaders))."\r\n\r\n");

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
                if ($buffererProgress <= $progressBar->step && $this->bufferer->isBuffering()) {
                    yield $this->bufferer->waitForWrite();

                    continue;
                }

                if (($chunk = yield $this->inputStream->read()) !== null) {
                    yield $socket->write($chunk);

                    $progressBar->max = $this->bufferer->getCurrentProgress();
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
            $progressBar->finish();
            $this->logger->debug("$remoteAddress finished download");
        } catch (StreamException $exception) {
            $this->logger->debug("$remoteAddress aborted download");
        }

        $socket->end();
    }
}
