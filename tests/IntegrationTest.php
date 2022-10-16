<?php

declare(strict_types=1);

namespace Ostrolucky\Stdinho\Tests;

use Psr\Log\LoggerInterface;
use function Amp\asyncCoroutine;
use Amp\ByteStream\InputStream;
use Amp\ByteStream\OutputStream;
use Amp\Deferred;
use Amp\Delayed;
use Amp\Loop;
use Amp\Socket\Server;
use Amp\Socket\Socket;
use Amp\Socket\SocketAddress;
use Amp\Success;
use Ostrolucky\Stdinho\Bufferer\PipeBufferer;
use Ostrolucky\Stdinho\Responder;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Formatter\OutputFormatterInterface;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\ConsoleSectionOutput;

class IntegrationTest extends TestCase
{
    public function testBufferOverflow(): void
    {
        $buffererInput = $this->createMock(InputStream::class);
        $buffererOutput = $this->createMock(OutputStream::class);
        $responderInputStream = $this->createMock(InputStream::class);
        $consoleOutput = $this->createMock(ConsoleOutput::class);
        $section = $this->createMock(ConsoleSectionOutput::class);
        $outputFormatter = $this->createMock(OutputFormatterInterface::class);
        $resource = fopen('php://memory', 'rw');
        $server = new Server($resource);
        $logger = $this->createMock(LoggerInterface::class);

        $bufferer = new PipeBufferer($buffererInput, $section, $logger, $buffererOutput, $server, new Success(), 3);
        $responder = new Responder($logger, $bufferer, $consoleOutput, [], $responderInputStream, new Deferred());

        $socket = $this->createMock(Socket::class);

        $consoleOutput->method('section')->willReturn($section);
        $outputFormatter->method('isDecorated')->willReturn(false);
        $outputFormatter->method('format')->willReturn('');
        $section->method('getFormatter')->willReturn($outputFormatter);
        $socket->method('read')->willReturn(new Success(''));
        $socket->method('getRemoteAddress')->willReturn(new SocketAddress(''));

        $buffererInput->method('read')->willReturn(
            new Delayed(0, $foo = 'foo'),
            new Delayed(0, $bar = 'bar'),
            new Delayed(0, $baz = 'baz'),
            new Success()
        );

        $buffererOutput
            ->expects(static::exactly(1))
            ->method('write')
            ->willReturn(new Success())
        ;

        $responderInputStream
            ->expects(static::exactly(2))
            ->method('read')
            ->willReturn(new Success($foo), new Success())
        ;

        $socket
            ->expects(static::exactly(4))
            ->method('write')
            ->withConsecutive([Assert::stringContains('HTTP/1.1 200 OK')], [$foo], [$bar], [$baz])
            ->willReturn(new Success())
        ;

        $logger->expects(self::once())->method('warning')->with(self::stringStartsWith('Max buffer size reached'));

        Loop::run(function() use ($socket, $bufferer, $responder): void {
            asyncCoroutine($bufferer)();
            asyncCoroutine($responder)($socket);
        });

        static::assertFalse(is_resource($resource));
    }
}
