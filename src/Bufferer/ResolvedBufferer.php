<?php

declare(strict_types=1);

namespace Ostrolucky\Stdinho\Bufferer;

use Amp\Promise;
use Amp\Success;

class ResolvedBufferer extends AbstractBufferer
{
    private int $filesize;
    private string $mimeType;

    public function __construct(string $filePath)
    {
        parent::__construct($filePath);
        $this->filesize = filesize($filePath);
        $this->mimeType = (new \finfo(FILEINFO_MIME))->file($filePath);
    }

    public function __invoke(): Promise
    {
        return new Success();
    }

    public function isBuffering(): bool
    {
        return false;
    }

    public function waitForWrite(): Promise
    {
        return new Success();
    }

    public function getMimeType(): Promise
    {
        return new Success($this->mimeType);
    }

    public function getCurrentProgress(): int
    {
        return $this->filesize;
    }
}
