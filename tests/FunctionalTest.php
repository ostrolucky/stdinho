<?php

declare(strict_types=1);

namespace Ostrolucky\Stdinho\Tests;

use Amp\Artax\DefaultClient;
use Amp\Artax\Response;
use Amp\Delayed;
use Amp\Loop;
use Amp\Process\Process;
use PHPUnit\Framework\TestCase;
use function Amp\Promise\wait;

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

    public static function setUpBeforeClass(): void
    {
        array_map('unlink', glob(__DIR__.'/*.coverage.cov'));
    }

    protected function setUp(): void
    {
        Loop::run(function () {
            $this->process = new Process(
                'php '.__DIR__.'/coverage-enabling-bin-wrapper.php --connections-limit=1 localhost:1338'
            );

            yield $this->process->start();

            $this->httpClient = new DefaultClient();
            $this->httpClient->setOption(DefaultClient::OP_TRANSFER_TIMEOUT, 400);

            // wait till server booted and listens to connections
            yield $this->process->getStdout()->read();
        });
    }

    protected function tearDown(): void
    {
        $this->process->getStdin()->close();
        // have to wait due to https://github.com/amphp/process/issues/33
        wait($this->process->join());
    }

    /**
     * Tests syncing of PipeBufferer and Responder, essentially tests commit be4421d6911888e839f3a850b650ee2c6de30b25
     */
    public function testStdinIsWrittenToSocketASAP(): void
    {
        Loop::run(function () {
            yield $this->process->getStdin()->write('foo');
            yield new Delayed(20);

            /** @var Response $response */
            $response = yield $this->httpClient->request('http://localhost:1338');
            self::assertEquals('foo', yield $response->getBody()->read()); // drain the buffer

            yield $this->process->getStdin()->write('bar');
            yield new Delayed(10);
            // should result in timeout here if not synced properly
            self::assertEquals('bar', yield $response->getBody()->read());
        });
    }
}
