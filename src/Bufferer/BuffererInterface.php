<?php

declare(strict_types=1);

namespace Ostrolucky\Stdinho\Bufferer;

use Amp\Promise;

interface BuffererInterface
{
    public function __invoke(): Promise;

    public function getFilePath(): string;

    public function isBuffering(): bool;

    public function waitForWrite(): Promise;

    public function getMimeType(): Promise;

    public function getCurrentProgress(): int;
}
