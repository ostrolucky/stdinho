<?php

namespace Ostrolucky\StdinFileServer;

use Amp\ByteStream\StreamException;
use Amp\File\Handle;
use Amp\Socket\Socket;
use Psr\Log\LoggerInterface;

class Responder
{
    private $logger;
    private $stdinPersister;

    public function __construct(LoggerInterface $logger, Bufferer $stdinPersister)
    {
        $this->logger = $logger;
        $this->stdinPersister = $stdinPersister;
    }

    public function __invoke(Socket $socket)
    {
        $remoteAddress = $socket->getRemoteAddress();
        $this->logger->info("Accepted connection from $remoteAddress");

        /** @var Handle $handle */
        $handle = yield \Amp\File\open($this->stdinPersister->getFilePath(), 'r');

        yield $socket->write(sprintf("HTTP/1.1 200 OK\nContent-Type: %s\n\n", $this->stdinPersister->getMimeType()));

        try {
            while (null !== $chunk = yield $handle->read()) {
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