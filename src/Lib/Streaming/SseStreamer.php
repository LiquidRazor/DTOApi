<?php

declare(strict_types=1);

namespace LiquidRazor\DtoApiBundle\Lib\Streaming;

use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Serializer\SerializerInterface;

final readonly class SseStreamer
{
    public function __construct(
        private SerializerInterface $serializer,
        private int                 $heartbeatSeconds = 15,
    ) {}

    /**
     * @param iterable<mixed> $iterable items; each item can be:
     *  - SseEvent
     *  - string (sent as data: <string>)
     *  - array|object (JSON-encoded as data)
     */
    public function stream(iterable $iterable, int $status = 200, array $headers = []): StreamedResponse
    {
        $headers = array_replace([
            'Content-Type'        => 'text/event-stream; charset=utf-8',
            'Cache-Control'       => 'no-cache, no-transform',
            'X-Accel-Buffering'   => 'no',
            'Connection'          => 'keep-alive',
        ], $headers);

        $lastBeat = time();

        return new StreamedResponse(function () use ($iterable, &$lastBeat) {
            OutputBufferUtil::disableAll();

            foreach ($iterable as $item) {
                if (OutputBufferUtil::aborted()) { break; }

                $event = $this->normalize($item);

                if ($event->event !== null) {
                    echo 'event: ', $this->escape($event->event), "\n";
                }
                if ($event->id !== null) {
                    echo 'id: ', $this->escape($event->id), "\n";
                }
                if ($event->retryMs !== null) {
                    echo 'retry: ', (int) $event->retryMs, "\n";
                }

                // data lines (can be multi-line; split as required by SSE)
                $payload = is_string($event->data)
                    ? $event->data
                    : $this->serializer->serialize($event->data, 'json');

                foreach (preg_split("/\r\n|\n|\r/", $payload) as $line) {
                    echo 'data: ', $line, "\n";
                }
                echo "\n"; // final newline to dispatch event
                flush();

                if ($this->heartbeatSeconds > 0 && (time() - $lastBeat) >= $this->heartbeatSeconds) {
                    echo ":\n\n"; // comment line as heartbeat
                    flush();
                    $lastBeat = time();
                }
            }
        }, $status, $headers);
    }

    private function normalize(mixed $item): SseEvent
    {
        if ($item instanceof SseEvent) { return $item; }
        if (is_array($item)) {
            // accepts ['data'=>..., 'event'=>?, 'id'=>?, 'retryMs'=>?]
            return new SseEvent(
                data:   $item['data']   ?? $item,
                event:  $item['event']  ?? null,
                id:     $item['id']     ?? null,
                retryMs:$item['retryMs']?? null
            );
        }
        return new SseEvent($item);
    }

    private function escape(string $s): string
    {
        // Minimal escaping for event/id lines (no newlines)
        return str_replace(["\r", "\n"], ' ', $s);
    }
}
