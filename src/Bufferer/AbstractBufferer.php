<?php

declare(strict_types=1);

namespace Ostrolucky\Stdinho\Bufferer;

use Amp\ByteStream\InputStream;
use Amp\Promise;

abstract class AbstractBufferer
{
    public ?InputStream $inputStream = null;

    public function __construct(public string $filePath)
    {
    }

    abstract public function __invoke(): Promise;

    abstract public function isBuffering(): bool;

    abstract public function waitForWrite(): Promise;

    abstract public function getMimeType(): Promise;

    abstract public function getCurrentProgress(): int;
}
