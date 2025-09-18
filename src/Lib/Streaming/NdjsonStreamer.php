<?php

declare(strict_types=1);

namespace LiquidRazor\DtoApiBundle\Lib\Streaming;

use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Serializer\SerializerInterface;

final readonly class NdjsonStreamer
{
    public function __construct(
        private SerializerInterface $serializer,
        private int                 $heartbeatSeconds = 15,
    ) {}

    /**
     * @param iterable<mixed> $iterable items to serialize line-by-line
     */
    public function stream(iterable $iterable, int $status = 200, array $headers = []): StreamedResponse
    {
        $headers = array_replace([
            'Content-Type'        => 'application/x-ndjson; charset=utf-8',
            'Cache-Control'       => 'no-cache, no-transform',
            'X-Accel-Buffering'   => 'no',   // Nginx: disable proxy buffering
            'Connection'          => 'keep-alive',
        ], $headers);

        $lastBeat = time();

        return new StreamedResponse(function () use ($iterable, &$lastBeat) {
            OutputBufferUtil::disableAll();

            foreach ($iterable as $item) {
                if (OutputBufferUtil::aborted()) { break; }

                $json = is_string($item)
                    ? $item
                    : $this->serializer->serialize($item, 'json');

                echo $json, "\n";
                flush();

                if ($this->heartbeatSeconds > 0 && (time() - $lastBeat) >= $this->heartbeatSeconds) {
                    echo "{}\n"; // tiny keepalive frame
                    flush();
                    $lastBeat = time();
                }
            }
        }, $status, $headers);
    }
}
