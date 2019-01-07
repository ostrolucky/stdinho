<?php

namespace Ostrolucky\Stdinho;

use Amp\ByteStream\StreamException;
use Amp\File\Handle;
use Amp\Socket\Socket;
use Ostrolucky\Stdinho\Bufferer\BuffererInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Output\ConsoleOutput;

class Responder
{
    private $logger;
    private $bufferer;
    private $consoleOutput;

    public function __construct(LoggerInterface $logger, BuffererInterface $bufferer, ConsoleOutput $consoleOutput)
    {
        $this->logger = $logger;
        $this->bufferer = $bufferer;
        $this->consoleOutput = $consoleOutput;
    }

    public function __invoke(Socket $socket): \Generator
    {
        $remoteAddress = $socket->getRemoteAddress();
        $this->logger->debug("Accepted connection from $remoteAddress:\n" . trim(yield $socket->read()));

        /** @var Handle $handle */
        $handle = yield \Amp\File\open($this->bufferer->getFilePath(), 'rb');

        $header = "HTTP/1.1 200 OK\r\nContent-Type: " . yield $this->bufferer->getMimeType();
        if ($chunked = $this->bufferer->isBuffering()) {
            $header .= "\r\nTransfer-Encoding: chunked";
        } else {
            $header .= "\r\nContent-Length: {$this->bufferer->getCurrentProgress()}";
        }

        $header .= "\r\nConnection: close";

        yield $socket->write("$header\r\n\r\n");

        $progressBar = new ProgressBar(
            $this->consoleOutput->section(),
            $this->bufferer->getCurrentProgress(),
            'portal',
            $remoteAddress
        );

        try {
            while (($chunk = yield $handle->read()) || $this->bufferer->isBuffering()) {
                // we reached end of the buffer, but it's still buffering
                if ($chunk === null) {
                    yield $this->bufferer->waitForWrite(); // wait until bufferer makes another write to read again
                    continue;
                }

                yield $socket->write($chunked ? sprintf("%x\r\n%s\r\n", strlen($chunk), $chunk) : $chunk);

                $progressBar->max = $this->bufferer->getCurrentProgress();
                $progressBar->advance(strlen($chunk));
            };

            if ($chunked) {
                yield $socket->write("0\r\n\r\n");
            }
            $progressBar->finish();
            $this->logger->debug("$remoteAddress finished download");
        } catch (StreamException $exception) {
            $this->logger->debug("$remoteAddress aborted download");
        }

        $handle->end();
        $socket->end();
    }
}