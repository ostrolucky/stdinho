<?php

declare(strict_types=1);

namespace Ostrolucky\Stdinho;

use Amp\ByteStream\ResourceInputStream;
use Amp\ByteStream\StreamException;
use Amp\Socket\Socket;
use Ostrolucky\Stdinho\Bufferer\BuffererInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Output\ConsoleOutput;

class Responder
{
    /**
     * @var LoggerInterface
     */
    private $logger;
    /**
     * @var BuffererInterface
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
     * @param string[] $customHttpHeaders
     */
    public function __construct(
        LoggerInterface $logger,
        BuffererInterface $bufferer,
        ConsoleOutput $consoleOutput,
        array $customHttpHeaders
    ) {
        $this->logger = $logger;
        $this->bufferer = $bufferer;
        $this->consoleOutput = $consoleOutput;
        $this->customHttpHeaders = $customHttpHeaders;
    }

    public function __invoke(Socket $socket): \Generator
    {
        $remoteAddress = $socket->getRemoteAddress();
        $this->logger->debug("Accepted connection from $remoteAddress:\n".trim(yield $socket->read()));

        $header = [
            'HTTP/1.1 200 OK',
            'Content-Disposition: inline; filename="'.basename($this->bufferer->getFilePath()).'"',
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

        $handle = new ResourceInputStream(fopen($this->bufferer->getFilePath(), 'rb'));

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

                if (($chunk = yield $handle->read()) === null) {
                    break; // No more buffering and client caught up to it -> finish download
                }

                yield $socket->write($chunk);

                $progressBar->max = $this->bufferer->getCurrentProgress();
                $progressBar->advance(strlen($chunk));
            }
            $progressBar->finish();
            $this->logger->debug("$remoteAddress finished download");
        } catch (StreamException $exception) {
            $this->logger->debug("$remoteAddress aborted download");
        }

        $handle->close();
        $socket->end();
    }
}
