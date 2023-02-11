<?php

declare(strict_types=1);

namespace Ostrolucky\Stdinho\Bufferer;

use Amp\ByteStream\ReadableStream;
use Amp\Future;

abstract class AbstractBufferer
{
    public ?ReadableStream $inputStream = null;

    public function __construct(public string $filePath)
    {
    }

    abstract public function __invoke(): void;

    abstract public function isBuffering(): bool;

    abstract public function waitForWrite(): Future;

    abstract public function getMimeType(): Future;

    abstract public function getCurrentProgress(): int;
}
