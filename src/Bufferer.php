<?php

namespace Ostrolucky\Stdinho;

use Amp\ByteStream\InputStream;
use Amp\ByteStream\ResourceOutputStream;
use Amp\Promise;
use Psr\Log\LoggerInterface;

class Bufferer
{
    private $logger;
    private $inputStream;
    private $outputStream;
    private $output;

    private $mimeType;
    private $filePath;
    private $buffering = true;
    private $progressBar = 0;

    public function __construct(
        LoggerInterface $logger,
        InputStream $inputStream,
        ResourceOutputStream $outputStream,
        ConsoleSectionOutput $output
    ) {
        $this->logger = $logger;
        $this->inputStream = $inputStream;
        $this->outputStream = $outputStream;
        $this->output = $output;
        $this->mimeType = new \Amp\Deferred;
        $this->filePath = stream_get_meta_data($this->outputStream->getResource())['uri'];
        $this->progressBar = new ProgressBar($this->output, 0, 'buffer');
    }

    public function __invoke()
    {
        $this->logger->debug("Saving stdin to $this->filePath");

        $bytesDownloaded = 0;
        while (null !== $chunk = yield $this->inputStream->read()) {
            yield $this->outputStream->write($chunk);

            if ($bytesDownloaded === 0) {
                $mimeType = (new \finfo(FILEINFO_MIME))->buffer($chunk);
                $this->logger->debug("Stdin MIME type detected: $mimeType");
                $this->mimeType->resolve($mimeType);
                $this->output->writeln('');
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

    public function getProgressBar(): ProgressBar
    {
        return $this->progressBar;
    }
}