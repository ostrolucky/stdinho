<?php

namespace Ostrolucky\StdinFileServer;

use Amp\ByteStream\ResourceInputStream;
use Amp\Loop;
use Psr\Log\LoggerInterface;

class Bufferer
{
    private $logger;
    private $handle;

    private $mimeType;
    private $filePath;
    private $buffering = true;

    public function __construct(LoggerInterface $logger, $resource)
    {
        $this->logger = $logger;
        $this->handle = $resource;
    }

    public function __invoke($watcherId, $stream)
    {
        static $run = false;

        if (feof($stream)) {
            $this->logger->debug('Stdin transfer done');
            $this->buffering = false;
            Loop::cancel($watcherId);
            return;
        }

        if (!$run) {
            stream_set_blocking($stream, false);
        }

        fputs($this->handle, $data = fread($stream, ResourceInputStream::DEFAULT_CHUNK_SIZE));


        if (!$run) {
            $run = true;
            $this->filePath = stream_get_meta_data($this->handle)['uri'];
            $this->mimeType = (new \finfo(FILEINFO_MIME))->buffer($data);
            $this->logger->debug("Saving stdin to $this->filePath");
            $this->logger->debug("Stdin MIME type detected: $this->mimeType");
        }
    }

    public function getFilePath(): string
    {
        return $this->filePath;
    }

    public function getMimeType(): string
    {
        return $this->mimeType;
    }

    public function isBuffering(): bool
    {
        return $this->buffering;
    }
}