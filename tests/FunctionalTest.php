<?php

declare(strict_types=1);

namespace Ostrolucky\Stdinho\Tests;

use Amp\Delayed;
use Amp\Http\Client\HttpClient;
use Amp\Http\Client\HttpClientBuilder;
use Amp\Http\Client\Request;
use Amp\Http\Client\Response;
use Amp\Loop;
use Amp\Process\Process;
use PHPUnit\Framework\TestCase;
use function Amp\Promise\timeout;
use function Amp\Promise\wait;

class FunctionalTest extends TestCase
{
    /**
     * @var Process
     */
    private $process;
    /**
     * @var HttpClient
     */
    private $httpClient;
    /**
     * @var string
     */
    private $command;

    public static function setUpBeforeClass(): void
    {
        array_map('unlink', glob(__DIR__.'/*.coverage.cov'));
    }

    protected function setUp(): void
    {
        Loop::run(function (): void {
            $isCoverageEnabled = array_filter($_SERVER['argv'], function (string $arg) {
                return strpos($arg, '--coverage') === 0;
            });
            $executable = $isCoverageEnabled ? 'coverage-enabling-bin-wrapper.php' : '../bin/stdinho';
            $this->command = 'php '.__DIR__."/$executable --connections-limit=1 localhost:1338 ";
            $this->httpClient = HttpClientBuilder::buildDefault();
        });
    }

    protected function tearDown(): void
    {
        $this->process->getStdin()->close();
        // have to wait due to https://github.com/amphp/process/issues/33
        wait(timeout($this->process->join(), 500));
    }

    /**
     * Tests syncing of PipeBufferer and Responder, essentially tests commit be4421d6911888e839f3a850b650ee2c6de30b25
     *
     * @dataProvider cliArgumentsProvider
     */
    public function testStdinIsWrittenToSocketASAP(string $cliArguments): void
    {
        Loop::run(function () use ($cliArguments) {
            yield from $this->bootServer($cliArguments);

            yield $this->process->getStdin()->write('foo');
            yield new Delayed(60);

            /** @var Response $response */
            $response = yield $this->httpClient->request(new Request('http://localhost:1338'));
            self::assertEquals('foo', yield $response->getBody()->read()); // drain the buffer

            yield $this->process->getStdin()->write('bar');
            yield new Delayed(10);
            // should result in timeout here if not synced properly
            self::assertEquals('bar', yield $response->getBody()->read());
        });
    }

    /**
     * @return string[][]
     */
    public function cliArgumentsProvider(): array
    {
        return [[''], ['--buffer-size=0']];
    }

    private function bootServer(string $arguments): \Generator
    {
        $this->process = new Process($this->command.$arguments);
        yield $this->process->start();
        // wait till server booted and listens to connections
        yield $this->process->getStdout()->read();
    }
}
