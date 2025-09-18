<?php

declare(strict_types=1);

namespace LiquidRazor\DtoApiBundle\Lib\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
final readonly class DtoApiOperation
{
    /**
     * Constructor for DtoApiOperation attribute that configures API operation details.
     *
     * @param string $summary A brief summary of the API operation
     * @param string|null $description Detailed description of the API operation
     * @param string|null $tag The tag used in the OpenAPI description
     * @param string|null $request The request DTO class name
     * @param string|string[]|null $response The response DTO class name or array of possible responses
     * @param bool $stream Whether the response should be streamed
     * @param int[] $status Expected HTTP status codes for the response
     * @param bool $deprecated Whether the operation is marked as deprecated
     */
    public function __construct(
        public string $summary,
        public ?string $description = null,
        public ?string $tag = null,
        public ?string $request = null,
        public string|array|null $response = null,
        public bool $stream = false,
        public array $status = [200],
        public bool $deprecated = false
    ) {}
}