<?php

declare(strict_types=1);

namespace LiquidRazor\DtoApiBundle\Lib\Streaming;

/**
 * Simple SSE event wrapper. $data can be string|array|object (serialized to JSON).
 */
final class SseEvent
{
    public function __construct(
        public mixed $data,
        public ?string $event = null,
        public ?string $id = null,
        public ?int $retryMs = null,
    ) {}
}
