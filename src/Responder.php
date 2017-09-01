<?php

namespace Ostrolucky\StdinFileServer;

use Amp\ByteStream\StreamException;
use Amp\File\Handle;
use Amp\Socket\Socket;
use Psr\Log\LoggerInterface;

class Responder
{
    private $logger;
    private $bufferer;

    public function __construct(LoggerInterface $logger, Bufferer $bufferer)
    {
        $this->logger = $logger;
        $this->bufferer = $bufferer;
    }

    public function __invoke(Socket $socket)
    {
        $remoteAddress = $socket->getRemoteAddress();
        $this->logger->info("Accepted connection from $remoteAddress");

        /** @var Handle $handle */
        $handle = yield \Amp\File\open($this->bufferer->getFilePath(), 'r');

        yield $socket->write(sprintf("HTTP/1.1 200 OK\nContent-Type: %s\n\n", yield $this->bufferer->getMimeType()));

        try {
            while ($chunk = yield $handle->read() or $this->bufferer->isBuffering()) {
                // we reached end of the buffer, but it's still buffering
                if ($chunk === null) {
                    continue;
                }
//                echo strlen($chunk).PHP_EOL;
                yield $socket->write($chunk);
            };
            $this->logger->info("$remoteAddress finished download");
        } catch (StreamException $exception) {
            $this->logger->info("$remoteAddress aborted download");
        }

        $handle->end();
        $socket->end();

    }
}