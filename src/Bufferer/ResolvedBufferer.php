<?php

declare(strict_types=1);

namespace Ostrolucky\Stdinho\Bufferer;

use Amp\Promise;
use Amp\Success;

class ResolvedBufferer implements BuffererInterface
{
    /**
     * @var string
     */
    private $filePath;
    /**
     * @var int
     */
    private $filesize;
    /**
     * @var string
     */
    private $mimeType;

    public function __construct(string $filePath)
    {
        $this->filePath = $filePath;
        $this->filesize = filesize($filePath);
        $this->mimeType = (new \finfo(FILEINFO_MIME))->file($filePath);
    }

    public function __invoke(): Promise
    {
        return new Success();
    }

    public function getFilePath(): string
    {
        return $this->filePath;
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
