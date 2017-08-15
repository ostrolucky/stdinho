<?php

namespace Ostrolucky\StdinFileServer;

use Aerys\Request;
use Aerys\Response;
use Amp\File\Handle;
use Psr\Log\LoggerInterface;
use function Amp\File\open;

class Responder
{
    private $logger;
    private $outputFilePath;

    public function __construct(LoggerInterface $logger, $outputFilePath)
    {
        $this->logger = $logger;
        $this->outputFilePath = $outputFilePath;
    }

    public function __invoke(Request $request, Response $response)
    {
        $connectionInfo = $request->getConnectionInfo();
        $client = sprintf("%s:%s", $connectionInfo['client_addr'], $connectionInfo['client_port']);

        $this->logger->info("$client started download");

        $response->setHeader('content-type', 'application/octet-stream');

        /** @var Handle $handle */
        $handle = yield open($this->outputFilePath, 'r');

        while (null !== $chunk = yield $handle->read()) {
            yield $response->write($chunk);
        }

        $this->logger->info("$client finished download");
    }
}