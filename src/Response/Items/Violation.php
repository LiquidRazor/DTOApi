<?php

namespace LiquidRazor\DtoApiBundle\Response\Items;

use LiquidRazor\DtoApiBundle\Lib\Attributes\DtoApiProperty;
use LiquidRazor\DtoApiBundle\Lib\Attributes\DtoApiResponseItem;

#[DtoApiResponseItem(
    name: 'Violation',
    description: 'Violation schema'
)]
final readonly class Violation
{
    public function __construct(
        #[DtoApiProperty(
            name: 'property',
            description: 'The property path that failed validation',
            type: 'string',
            example: 'age'
        )]
        public string $property,

        #[DtoApiProperty(
            name: 'message',
            description: 'Human readable error message',
            type: 'string',
            example: 'This value should be less than or equal to 100.'
        )]
        public string $message,
    ) {}
}
