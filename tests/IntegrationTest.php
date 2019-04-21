<?php

declare(strict_types=1);

namespace Ostrolucky\Stdinho\Tests;

use Amp\ByteStream\InputStream;
use Amp\ByteStream\OutputStream;
use Amp\Delayed;
use Amp\Loop;
use Amp\Socket\ClientSocket;
use Amp\Socket\Server;
use Amp\Success;
use Ostrolucky\Stdinho\Bufferer\PipeBufferer;
use Ostrolucky\Stdinho\Responder;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\TestCase;
use Psr\Log\Test\TestLogger;
use Symfony\Component\Console\Formatter\OutputFormatterInterface;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\ConsoleSectionOutput;
use function Amp\asyncCoroutine;

class IntegrationTest extends TestCase
{
    public function testBufferOverflow(): void
    {
        $buffererInputStream = $this->createMock(InputStream::class);
        $buffererOutputStream = $this->createMock(OutputStream::class);
        $responderInputStream = $this->createMock(InputStream::class);
        $consoleOutput = $this->createMock(ConsoleOutput::class);
        $sectionOutput = $this->createMock(ConsoleSectionOutput::class);
        $server = $this->createMock(Server::class);
        $logger = new TestLogger();

        $bufferer = new PipeBufferer($logger, $buffererInputStream, $buffererOutputStream, $sectionOutput, $server, 3);
        $responder = new Responder($logger, $bufferer, $consoleOutput, [], $responderInputStream);

        $socket = $this->getMockBuilder(ClientSocket::class)
            ->disableOriginalConstructor()
            ->setMethods(['read', 'write', 'getRemoteAddress', 'end'])
            ->getMock()
        ;

        $consoleOutput->method('section')->willReturn($sectionOutput);
        $sectionOutput->method('getFormatter')->willReturn($this->createMock(OutputFormatterInterface::class));
        $socket->method('read')->willReturn(new Success(''));

        $buffererInputStream->method('read')->willReturn(
            new Delayed(0, $foo = 'foo'),
            new Delayed(0, $bar = 'bar'),
            new Delayed(0, $baz = 'baz'),
            new Success()
        );

        $buffererOutputStream
            ->expects($this->exactly(1))
            ->method('write')
            ->willReturn(new Success())
        ;

        $responderInputStream
            ->expects($this->exactly(2))
            ->method('read')
            ->willReturn(new Success($foo), new Success())
        ;

        $socket
            ->expects($this->exactly(4))
            ->method('write')
            ->withConsecutive([Assert::stringContains('HTTP/1.1 200 OK')], [$foo], [$bar], [$baz])
            ->willReturn(new Success())
        ;

        $server->expects($this->exactly(1))->method('close');

        Loop::run(function () use ($socket, $bufferer, $responder): void {
            asyncCoroutine($bufferer)();
            asyncCoroutine($responder)($socket);
        });

        self::assertTrue($logger->hasWarningThatContains('Max buffer size reached'));
    }
}
