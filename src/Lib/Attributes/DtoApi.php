<?php

declare(strict_types=1);

namespace LiquidRazor\DtoApiBundle\Lib\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
final readonly class DtoApi
{
    public function __construct(
        public ?string $name = null,
        public ?string $description = null
    ) {}
}