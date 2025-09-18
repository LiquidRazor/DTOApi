<?php

declare(strict_types=1);

namespace LiquidRazor\DtoApiBundle\Lib\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final readonly class DtoApiResponse
{
    public function __construct(
        public ?string $name = null,
        public ?string $description = null,
        public int     $status = 200,
        public ?string $contentType = 'application/json',
        public bool    $stream = false,
        /** @var class-string|null */
        public ?string $class = null // null means no body
    )
    {
    }
}