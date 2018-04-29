<?php

namespace Ostrolucky\Stdinho\Bufferer;

use Amp\Promise;

interface BuffererInterface
{
    public function __invoke();
    public function getFilePath(): string;
    public function isBuffering(): bool;
    public function getMimeType(): Promise;
    public function getCurrentProgress(): int;
}