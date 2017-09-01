<?php

namespace Ostrolucky\StdinFileServer;

use Amp\ByteStream\InputStream;
use Amp\ByteStream\ResourceOutputStream;
use Amp\Promise;
use Psr\Log\LoggerInterface;

class Bufferer
{
    private $logger;
    private $inputStream;
    private $outputStream;

    private $mimeType;
    private $filePath;
    private $buffering = true;

    public function __construct(LoggerInterface $logger, InputStream $inputStream, ResourceOutputStream $outputStream)
    {
        $this->logger = $logger;
        $this->inputStream = $inputStream;
        $this->outputStream = $outputStream;
        $this->mimeType = new \Amp\Deferred;
        $this->filePath = stream_get_meta_data($this->outputStream->getResource())['uri'];
    }

    public function __invoke()
    {
        $this->logger->debug("Saving stdin to $this->filePath");

        $firstRun = true;
        while (null !== $chunk = yield $this->inputStream->read()) {
            yield $this->outputStream->write($chunk);

            if ($firstRun) {
                $firstRun = false;
                $mimeType = (new \finfo(FILEINFO_MIME))->buffer($chunk);
                $this->logger->debug("Stdin MIME type detected: $mimeType");
                $this->mimeType->resolve($mimeType);
            }
        }

        $this->buffering = false;
        $this->logger->debug('Stdin transfer done');
    }

    public function getFilePath(): string
    {
        return $this->filePath;
    }

    public function getMimeType(): Promise
    {
        return $this->mimeType->promise();
    }

    public function isBuffering(): bool
    {
        return $this->buffering;
    }
}