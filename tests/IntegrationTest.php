<?php

declare(strict_types=1);

namespace Ostrolucky\Stdinho\Tests;

use Amp\ByteStream\ReadableStream;
use Amp\ByteStream\WritableStream;
use Amp\DeferredFuture;
use Amp\Future;
use Amp\Socket\BindContext;
use Amp\Socket\ResourceServerSocket;
use Amp\Socket\Socket;
use Amp\Socket\SocketAddress;
use Ostrolucky\Stdinho\Bufferer\PipeBufferer;
use Ostrolucky\Stdinho\Responder;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use SEEC\PhpUnit\Helper\ConsecutiveParams;
use Symfony\Component\Console\Formatter\OutputFormatterInterface;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\ConsoleSectionOutput;
use function Amp\async;

class IntegrationTest extends TestCase
{
    use ConsecutiveParams;

    public function testBufferOverflow(): void
    {
        $buffererInput = $this->createMock(ReadableStream::class);
        $buffererOutput = $this->createMock(WritableStream::class);
        $responderInputStream = $this->createMock(ReadableStream::class);
        $consoleOutput = $this->createMock(ConsoleOutput::class);
        $section = $this->createMock(ConsoleSectionOutput::class);
        $outputFormatter = $this->createMock(OutputFormatterInterface::class);
        $resource = fopen('php://memory', 'rw');
        $server = new ResourceServerSocket($resource, new BindContext());
        $logger = $this->createMock(LoggerInterface::class);

        $bufferer = new PipeBufferer($buffererInput, $section, $logger, $buffererOutput, $server, Future::complete(), 3);
        $responder = new Responder($logger, $bufferer, $consoleOutput, [], $responderInputStream, new DeferredFuture());

        $socket = $this->createMock(Socket::class);

        $consoleOutput->method('section')->willReturn($section);
        $outputFormatter->method('isDecorated')->willReturn(false);
        $outputFormatter->method('format')->willReturn('');
        $section->method('getFormatter')->willReturn($outputFormatter);
        $socket->method('read')->willReturn('');
        $socket->method('getRemoteAddress')->willReturn($this->createMock(SocketAddress::class));
        $buffererInput->method('read')->willReturnOnConsecutiveCalls($foo = 'foo', $bar = 'bar', $baz = 'baz');
        $buffererOutput->expects(static::exactly(1))->method('write');
        $responderInputStream->expects(static::exactly(2))->method('read')->willReturnOnConsecutiveCalls($foo, null);

        $socket
            ->expects(static::exactly(4))
            ->method('write')
            ->with(...$this->withConsecutive([Assert::stringContains('HTTP/1.1 200 OK')], [$foo], [$bar], [$baz]))
        ;

        $logger->expects(self::once())->method('warning')->with(self::stringStartsWith('Max buffer size reached'));

        async($bufferer(...));
        async($responder(...), $socket)->await();

        static::assertFalse(is_resource($resource));
    }
}
