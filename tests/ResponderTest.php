<?php

declare(strict_types=1);

namespace Ostrolucky\Stdinho\Tests;

use Amp\Coroutine;
use Amp\Socket\ClientSocket;
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
            []
        );

        $socket = $this->getMockBuilder(ClientSocket::class)
            ->setConstructorArgs([$resource = fopen('php://memory', 'rw')])
            ->setMethods(['read', 'getRemoteAddress'])
            ->getMock()
        ;

        $socket->method('read')->willReturn(new Success(''));

        fclose($resource);

        wait(new Coroutine($responder->__invoke($socket)));

        self::assertTrue($logger->hasDebugThatContains('aborted download'));
    }
}
