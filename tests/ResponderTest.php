<?php

declare(strict_types=1);

namespace Ostrolucky\Stdinho\Tests;

use Amp\ByteStream\InputStream;
use Amp\ByteStream\ResourceOutputStream;
use Amp\Coroutine;
use Amp\Deferred;
use Amp\Socket\Socket;
use Amp\Socket\SocketAddress;
use Amp\Success;
use Ostrolucky\Stdinho\Bufferer\ResolvedBufferer;
use Ostrolucky\Stdinho\Responder;
use PHPUnit\Framework\TestCase;
use Psr\Log\Test\TestLogger;
use Symfony\Component\Console\Output\ConsoleOutput;
use function Amp\Promise\wait;

class ResponderTest extends TestCase
{
    public function testResponderHandlesClientAbruptDisconnect(): void
    {
        $responder = new Responder(
            $logger = new TestLogger(),
            new ResolvedBufferer(__FILE__),
            $this->createMock(ConsoleOutput::class),
            [],
            $this->createMock(InputStream::class),
            new Deferred()
        );

        $writer = new ResourceOutputStream($resource = fopen('php://memory', 'rwb'));
        $socket = $this->createMock(Socket::class);

        // TODO remove class_exists when we remove amphp/socket 0.x support (when amphp/artax supports amphp/socket 1.x)
        $socket->method('getRemoteAddress')->willReturn(class_exists(SocketAddress::class) ? new SocketAddress(''): '');
        $socket->method('read')->willReturn(new Success(''));
        $socket->method('write')->willReturnCallback(function(string $data) use ($writer) {
            return $writer->write($data);
        });

        fclose($resource);

        wait(new Coroutine($responder->__invoke($socket)));

        self::assertTrue($logger->hasDebugThatContains('aborted download'));
    }
}
