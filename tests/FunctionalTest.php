<?php

declare(strict_types=1);

namespace Ostrolucky\Stdinho\Tests;

use Amp\Artax\DefaultClient;
use Amp\Artax\Response;
use Amp\Loop;
use Amp\Process\Process;
use PHPUnit\Framework\TestCase;

class FunctionalTest extends TestCase
{
    /**
     * @var Process
     */
    private $process;
    /**
     * @var DefaultClient
     */
    private $httpClient;

    protected function setUp(): void
    {
        Loop::run(function () {
            $this->process = new Process(__DIR__.'/../bin/stdinho localhost:1338');
            yield $this->process->start();

            $this->httpClient = new DefaultClient();
            $this->httpClient->setOption(DefaultClient::OP_TRANSFER_TIMEOUT, 400);

            // wait till server booted and listens to connections
            self::assertStringStartsWith('Connection opened', yield $this->process->getStdout()->read());
        });
    }

    protected function tearDown(): void
    {
        $this->process->kill();
    }

    /**
     * Tests syncing of PipeBufferer and Responder, essentially tests commit be4421d6911888e839f3a850b650ee2c6de30b25
     */
    public function testStdinIsWrittenToSocketASAP(): void
    {
        Loop::run(function () {
            yield $this->process->getStdin()->write('foo');

            /** @var Response $response */
            $response = yield $this->httpClient->request('http://localhost:1338');
            self::assertEquals('foo', yield $response->getBody()->read()); // drain the buffer

            yield $this->process->getStdin()->write('bar');
            // should result in timeout here if not synced properly
            self::assertEquals('bar', yield $response->getBody()->read());
        });
    }
}
