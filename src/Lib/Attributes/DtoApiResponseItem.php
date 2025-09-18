<?php

declare(strict_types=1);

namespace LiquidRazor\DtoApiBundle\Lib\Attributes;

use Attribute;

/**
 * Marks a class as a reusable response component schema.
 * Useful for shared error/item shapes, even if not referenced yet.
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class DtoApiResponseItem
{
    public function __construct(
        public ?string $name = null,
        public ?string $description = null,
    ) {}
}
