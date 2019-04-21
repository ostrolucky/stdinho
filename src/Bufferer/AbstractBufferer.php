<?php

declare(strict_types=1);

namespace Ostrolucky\Stdinho\Bufferer;

use Amp\ByteStream\InputStream;
use Amp\Promise;

abstract class AbstractBufferer
{
    /**
     * @var string
     */
    public $filePath;
    /**
     * @var InputStream|null
     */
    public $inputStream;

    public function __construct(string $filePath)
    {
        $this->filePath = $filePath;
    }

    abstract public function __invoke(): Promise;

    abstract public function isBuffering(): bool;

    abstract public function waitForWrite(): Promise;

    abstract public function getMimeType(): Promise;

    abstract public function getCurrentProgress(): int;
}
