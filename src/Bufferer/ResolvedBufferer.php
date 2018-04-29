<?php


namespace Ostrolucky\Stdinho\Bufferer;

use Amp\Promise;

class ResolvedBufferer implements BuffererInterface
{
    private $filePath;
    private $filesize;
    private $mimeType;

    public function __construct(string $filePath)
    {
        $this->filePath = $filePath;
        $this->filesize = filesize($filePath);
        $this->mimeType = new \Amp\Deferred;
        $this->mimeType->resolve((new \finfo(FILEINFO_MIME))->file($filePath));
    }

    public function __invoke()
    {
        return $this->mimeType->promise();
    }

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
        return $this->mimeType->promise();
    }

    public function getCurrentProgress(): int
    {
        return $this->filesize;
    }
}