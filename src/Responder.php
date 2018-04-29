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

    public function __invoke(Socket $socket)
    {
        $remoteAddress = $socket->getRemoteAddress();
        $this->logger->debug("Accepted connection from $remoteAddress");

        /** @var Handle $handle */
        $handle = yield \Amp\File\open($this->bufferer->getFilePath(), 'r');

        yield $socket->write(sprintf("HTTP/1.1 200 OK\nContent-Type: %s\n\n", yield $this->bufferer->getMimeType()));

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
                    continue;
                }

                yield $socket->write($chunk);

                $progressBar->max = $this->bufferer->getCurrentProgress();
                $progressBar->advance(strlen($chunk));
            };
            $progressBar->finish();
            $this->logger->debug("$remoteAddress finished download");
        } catch (StreamException $exception) {
            $this->logger->debug("$remoteAddress aborted download");
        }

        $handle->end();
        $socket->end();
    }
}