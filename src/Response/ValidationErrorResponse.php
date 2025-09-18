<?php

namespace LiquidRazor\DtoApiBundle\Response;

use LiquidRazor\DtoApiBundle\Lib\Attributes\{DtoApiProperty, DtoApiResponse};
use LiquidRazor\DtoApiBundle\Response\Items\Violation;

#[DtoApiResponse(
    name: 'validation_error',
    description: 'Standardized response for validation errors',
    status: 422
)]
final readonly class ValidationErrorResponse
{
    public function __construct(
        #[DtoApiProperty(
            name: 'type',
            description: 'Error type',
            type: 'string',
            example: 'Validation error'
        )]
        public string $type = 'Validation error',

        #[DtoApiProperty(
            name: 'title',
            description: 'Error title',
            type: 'string',
            example: 'Invalid request body.'
        )]
        public string $title = 'Invalid request body.',

        #[DtoApiProperty(
            name: 'status',
            description: 'HTTP status code',
            type: 'integer',
            example: 422
        )]
        public int $status = 422,

        #[DtoApiProperty(
            name: 'violations',
            description: 'List of field-level violations',
            type: 'array',
            itemsRef: Violation::class
        )]
        public array $violations = [],
    ) {}
}
