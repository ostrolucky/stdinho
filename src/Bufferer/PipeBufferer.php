<?php

namespace Ostrolucky\Stdinho\Bufferer;

use Amp\ByteStream\InputStream;
use Amp\ByteStream\ResourceInputStream;
use Amp\ByteStream\ResourceOutputStream;
use Amp\Promise;
use Ostrolucky\Stdinho\ProgressBar;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Output\ConsoleSectionOutput;

class PipeBufferer implements BuffererInterface
{
    private $logger;
    private $inputStream;
    private $outputStream;

    private $mimeType;
    private $filePath;
    private $progressBar;

    private $buffering = true;

    /**
     * @param resource $inputStream
     */
    public function __construct(
        LoggerInterface $logger,
        $inputStream,
        ?string $outputPath,
        ConsoleSectionOutput $output
    ) {
        $this->logger = $logger;
        $this->inputStream = new ResourceInputStream($inputStream);
        $this->outputStream = new ResourceOutputStream($fileOutput = $outputPath ? fopen($outputPath, 'w') : tmpfile());
        $this->mimeType = new \Amp\Deferred;
        $this->filePath = $outputPath ?: stream_get_meta_data($fileOutput)['uri'];
        $this->progressBar = new ProgressBar($output, 0, 'buffer');
    }

    public function __invoke(): \Generator
    {
        $this->logger->debug("Saving stdin to $this->filePath");

        $bytesDownloaded = 0;
        while (null !== $chunk = yield $this->inputStream->read()) {
            yield $this->outputStream->write($chunk);

            if ($bytesDownloaded === 0) {
                $mimeType = (new \finfo(FILEINFO_MIME))->buffer($chunk);
                $this->logger->debug(sprintf('Stdin MIME type detected: "%s"', $mimeType));
                $this->mimeType->resolve($mimeType);
            }

            $this->progressBar->setProgress($bytesDownloaded += strlen($chunk));
        }

        $this->buffering = false;
        $this->progressBar->finish();
        $this->logger->debug("Stdin transfer done, $bytesDownloaded bytes downloaded");
    }

    public function getFilePath(): string
    {
        return $this->filePath;
    }

    public function getMimeType(): Promise
    {
        return $this->mimeType->promise();
    }

    public function isBuffering(): bool
    {
        return $this->buffering;
    }

    public function getCurrentProgress(): int
    {
        return $this->progressBar->step;
    }
}