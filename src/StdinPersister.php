<?php

namespace Ostrolucky\StdinFileServer;

use Amp\Loop;
use Psr\Log\LoggerInterface;

class StdinPersister
{
    private $logger;
    private $handle;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
        $this->handle = tmpfile();
        stream_set_blocking(STDIN, false);
    }

    public function __invoke($watcherId, $stream)
    {
        if (feof(STDIN)) {
            $this->logger->debug('Stdin transfer done');
            Loop::cancel($watcherId);
            return;
        }

        fputs($this->handle, fread($stream, 4096));
    }

    public function getHandleFilePath()
    {
        return stream_get_meta_data($this->handle)['uri'];
    }
}