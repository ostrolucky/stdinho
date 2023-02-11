<?php

declare(strict_types=1);

namespace Ostrolucky\Stdinho\Bufferer;

use Amp\Future;

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

    public function __invoke(): void
    {
    }

    public function isBuffering(): bool
    {
        return false;
    }

    public function waitForWrite(): Future
    {
        return Future::complete();
    }

    public function getMimeType(): Future
    {
        return Future::complete($this->mimeType);
    }

    public function getCurrentProgress(): int
    {
        return $this->filesize;
    }
}
