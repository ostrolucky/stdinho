<?php


namespace Ostrolucky\Stdinho\Bufferer;

use Amp\Promise;
use Amp\Success;

class ResolvedBufferer implements BuffererInterface
{
    private $filePath;
    private $filesize;
    private $mimeType;

    public function __construct(string $filePath)
    {
        $this->filePath = $filePath;
        $this->filesize = filesize($filePath);
        $this->mimeType = (new \finfo(FILEINFO_MIME))->file($filePath);
    }

    public function __invoke() {}

    public function getFilePath(): string
    {
        return $this->filePath;
    }

    public function isBuffering(): bool
    {
        return false;
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