<?php

namespace Ostrolucky\StdinFileServer;

use Amp\ByteStream\StreamException;
use Amp\File\Handle;
use Amp\Socket\Socket;
use Psr\Log\LoggerInterface;

class Responder
{
    private $logger;
    private $outputFilePath;
    private $contentType;

    public function __construct(LoggerInterface $logger, $outputFilePath)
    {
        $this->logger = $logger;
        $this->outputFilePath = $outputFilePath;
    }

    public function __invoke(Socket $socket)
    {
        $remoteAddress = $socket->getRemoteAddress();
        $this->logger->info("Accepted connection from $remoteAddress");

        if ($this->contentType === null) {
            $this->contentType = mime_content_type($this->outputFilePath);
            $this->logger->debug("Content-type detected: $this->contentType");
        }

        /** @var Handle $handle */
        $handle = yield \Amp\File\open($this->outputFilePath, 'r');

        yield $socket->write("HTTP/1.1 200 OK\nContent-Type: $this->contentType\n\n");

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