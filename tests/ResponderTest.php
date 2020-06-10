<?php

declare(strict_types=1);

namespace Ostrolucky\Stdinho\Tests;

use Amp\ByteStream\InputStream;
use Amp\ByteStream\ResourceOutputStream;
use Amp\Coroutine;
use Amp\Deferred;
use function Amp\Promise\wait;
use Amp\Socket\Socket;
use Amp\Socket\SocketAddress;
use Amp\Success;
use Ostrolucky\Stdinho\Bufferer\ResolvedBufferer;
use Ostrolucky\Stdinho\Responder;
use PHPUnit\Framework\TestCase;
use Psr\Log\Test\TestLogger;
use Symfony\Component\Console\Formatter\OutputFormatterInterface;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\ConsoleSectionOutput;

class ResponderTest extends TestCase
{
    public function testResponderHandlesClientAbruptDisconnect(): void
    {
        $responder = new Responder(
            $logger = new TestLogger(),
            new ResolvedBufferer(__FILE__),
            $output = $this->createMock(ConsoleOutput::class),
            [],
            $this->createMock(InputStream::class),
            new Deferred()
        );
        $outputFormatter = $this->createMock(OutputFormatterInterface::class);
        $outputFormatter->method('isDecorated')->willReturn(false);

        $output->method('section')->willReturn($sectionOutput = $this->createMock(ConsoleSectionOutput::class));
        $sectionOutput->method('getFormatter')->willReturn($outputFormatter);
        $writer = new ResourceOutputStream($resource = fopen('php://memory', 'rwb'));
        $socket = $this->createMock(Socket::class);

        $socket->method('getRemoteAddress')->willReturn(new SocketAddress(''));
        $socket->method('read')->willReturn(new Success(''));
        $socket->method('write')->willReturnCallback(function(string $data) use ($writer) {
            return $writer->write($data);
        });

        fclose($resource);

        wait(new Coroutine($responder->__invoke($socket)));

        static::assertTrue($logger->hasDebugThatContains('aborted download'));
    }
}
