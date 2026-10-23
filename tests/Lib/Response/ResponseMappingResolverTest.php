<?php

declare(strict_types=1);

namespace LiquidRazor\DtoApiBundle\Tests\Lib\Response;

use LiquidRazor\DtoApiBundle\Lib\Attributes\DtoApiResponse;
use LiquidRazor\DtoApiBundle\Lib\Response\ResponseMappingResolver;
use PHPUnit\Framework\TestCase;

final class ResponseMappingResolverTest extends TestCase
{
    public function testUnannotatedOperationResponseProvidesCompleteMetadata(): void
    {
        $mapping = (new ResponseMappingResolver())->resolve([], [UnannotatedPreviewResponse::class]);

        self::assertSame([[
            'status' => 200,
            'class' => UnannotatedPreviewResponse::class,
            'contentType' => 'application/json',
            'stream' => false,
            'name' => null,
            'description' => null,
        ]], $mapping);
    }

    public function testAnnotatedOperationResponseKeepsItsMetadata(): void
    {
        $mapping = (new ResponseMappingResolver())->resolve([], [AnnotatedPreviewResponse::class]);

        self::assertSame([[
            'status' => 201,
            'class' => AnnotatedPreviewResponse::class,
            'contentType' => 'application/json',
            'stream' => false,
            'name' => 'Preview',
            'description' => 'Credit preview',
        ]], $mapping);
    }

    public function testMethodLevelResponseTakesPrecedenceOverOperationFallback(): void
    {
        $mapping = (new ResponseMappingResolver())->resolve([[
            'status' => 200,
            'class' => null,
            'contentType' => 'text/plain',
            'stream' => false,
            'name' => 'Explicit',
            'description' => 'Method response',
        ]], [UnannotatedPreviewResponse::class]);

        self::assertSame([[
            'status' => 200,
            'class' => null,
            'contentType' => 'text/plain',
            'stream' => false,
            'name' => 'Explicit',
            'description' => 'Method response',
        ]], $mapping);
    }
}

final class UnannotatedPreviewResponse
{
}

#[DtoApiResponse(name: 'Preview', description: 'Credit preview', status: 201)]
final class AnnotatedPreviewResponse
{
}
