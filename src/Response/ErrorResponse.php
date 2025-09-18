<?php

namespace LiquidRazor\DtoApiBundle\Response;

use LiquidRazor\DtoApiBundle\Lib\Attributes\DtoApiProperty;
use LiquidRazor\DtoApiBundle\Lib\Attributes\DtoApiResponse;

#[DtoApiResponse(
    name: 'testA',
    description: 'initial test of DTO Response attribute',
    status: 500
)]
final class ErrorResponse
{
    public function __construct(
        #[DtoApiProperty(
            name: 'message',
            description: 'Error message',
            type: 'string',
            nullable: true,
            example: 'Something went wrong',
        )]
        public string $message
    ){}
}
