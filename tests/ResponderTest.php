<?php

declare(strict_types=1);

namespace Ostrolucky\Stdinho\Tests;

use Amp\ByteStream\ReadableStream;
use Amp\ByteStream\WritableResourceStream;
use Amp\DeferredFuture;
use Amp\Socket\Socket;
use Amp\Socket\SocketAddress;
use Ostrolucky\Stdinho\Bufferer\ResolvedBufferer;
use Ostrolucky\Stdinho\Responder;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use SEEC\PhpUnit\Helper\ConsecutiveParams;
use Symfony\Component\Console\Formatter\OutputFormatterInterface;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\ConsoleSectionOutput;

class ResponderTest extends TestCase
{
    use ConsecutiveParams;

    public function testResponderHandlesClientAbruptDisconnect(): void
    {
        $responder = new Responder(
            $logger = $this->createMock(LoggerInterface::class),
            new ResolvedBufferer(__FILE__),
            $output = $this->createMock(ConsoleOutput::class),
            [],
            $this->createMock(ReadableStream::class),
            new DeferredFuture()
        );
        $logger->expects(self::exactly(2))->method('debug')->with(...$this->withConsecutive(
                [self::stringStartsWith('Accepted connection')],
                [self::stringContains('aborted download')],
        ));
        $outputFormatter = $this->createMock(OutputFormatterInterface::class);
        $outputFormatter->method('isDecorated')->willReturn(false);
        $outputFormatter->method('format')->willReturn('');

        $output->method('section')->willReturn($sectionOutput = $this->createMock(ConsoleSectionOutput::class));
        $sectionOutput->method('getFormatter')->willReturn($outputFormatter);
        $writer = new WritableResourceStream($resource = fopen('php://memory', 'rwb'));
        $socket = $this->createMock(Socket::class);

        $socket->method('getRemoteAddress')->willReturn($this->createMock(SocketAddress::class));
        $socket->method('read')->willReturn('');
        $socket->method('write')->willReturnCallback(fn(string $data) => $writer->write($data));

        fclose($resource);

        $responder->__invoke($socket);
    }
}
