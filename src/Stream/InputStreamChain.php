<?php

declare(strict_types=1);

namespace Ostrolucky\Stdinho\Stream;

use Amp\ByteStream\InputStream;
use Amp\Promise;
use Amp\Success;
use Throwable;

/**
 * Goes through all registered InputStreams and proxies read() calls to them.
 * When one InputStream is finished (returns NULL), it removes it from stack and continues same operation with next
 * InputStream in stack, while guaranteeing NULL response from previous InputStream does not leak.
 * This happens until either all streams in stack were depleted, OR if beginning of one of the streams returns NULL.
 */
class InputStreamChain implements InputStream
{
    /**
     * @var InputStream[]
     */
    private $inputStreams = [];

    /**
     * @param InputStream[] $inputStreams
     */
    public function __construct(InputStream ...$inputStreams)
    {
        $this->inputStreams = $inputStreams;
    }

    public function read(): Promise
    {
        foreach ($this->inputStreams as $key => $inputStream) {
            $promise = $inputStream->read();
            $onResolved = function (?Throwable $reason, $value) use ($key, &$promise, &$onResolved): void {
                if ($value !== null) {
                    return;
                }

                if (isset($this->inputStreams[$key + 1])) {
                    $promise = $this->inputStreams[$key + 1]->read();
                }

                unset($this->inputStreams[$key]);
            };

            $promise->onResolve($onResolved);

            return $promise;
        }

        return new Success();
    }
}
