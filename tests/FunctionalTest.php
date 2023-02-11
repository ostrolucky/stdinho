<?php

declare(strict_types=1);

namespace Ostrolucky\Stdinho\Tests;

use Amp\Http\Client\HttpClient;
use Amp\Http\Client\HttpClientBuilder;
use Amp\Http\Client\Request;
use Amp\Process\Process;
use PHPUnit\Framework\TestCase;
use function Amp\delay;

class FunctionalTest extends TestCase
{
    private Process $process;
    private string $command;
    private HttpClient $httpClient;

    public static function setUpBeforeClass(): void
    {
        array_map(unlink(...), glob(__DIR__.'/*.coverage.cov'));
    }

    protected function setUp(): void
    {
        $isCoverageEnabled = array_filter($_SERVER['argv'], fn(string $arg) => str_starts_with($arg, '--coverage'));
        $executable = $isCoverageEnabled ? 'coverage-enabling-bin-wrapper.php' : '../bin/stdinho';
        $this->command = PHP_BINARY ." '".__DIR__."/$executable' --connections-limit=1 0.0.0.0:1338 ";
        $this->httpClient = HttpClientBuilder::buildDefault();
    }

    protected function tearDown(): void
    {
        $this->process->getStdin()->close();
        // have to wait due to https://github.com/amphp/process/issues/33
        $this->process->join();
    }

    /**
     * Tests syncing of PipeBufferer and Responder, essentially tests commit be4421d6911888e839f3a850b650ee2c6de30b25
     *
     * @testWith [""]
     *           ["--buffer-size=0"]
     */
    public function testStdinIsWrittenToSocketASAP(string $cliArguments): void
    {
        $this->bootServer($cliArguments);

        $this->process->getStdin()->write('foo');
        delay(0.5);

        $response = $this->httpClient->request(new Request('http://localhost:1338'));
        self::assertEquals('foo', $response->getBody()->read()); // drain the buffer

        $this->process->getStdin()->write('bar');
        delay(0.01);
        // should result in timeout here if not synced properly
        self::assertEquals('bar', $response->getBody()->read());
    }

    private function bootServer(string $arguments): void
    {
        $this->process = Process::start($this->command.$arguments);
        // wait till server booted and listens to connections
        $this->process->getStdout()->read();
    }
}
