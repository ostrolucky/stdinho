<?php

declare(strict_types=1);

namespace Ostrolucky\Stdinho\Tests\Stream;

use Amp\ByteStream\InMemoryStream;
use Ostrolucky\Stdinho\Stream\InputStreamChain;
use PHPUnit\Framework\TestCase;
use function Amp\Promise\wait;

class InputStreamChainTest extends TestCase
{
    public function testInputStreamChain(): void
    {
        $chain = new InputStreamChain(new InMemoryStream('foo'), new InMemoryStream(null), new InMemoryStream('bar'));

        self::assertEquals('foo', wait($chain->read()));
        self::assertNull(wait($chain->read()));
        self::assertEquals('bar', wait($chain->read()));
    }
}
