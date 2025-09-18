<?php

declare(strict_types=1);

namespace LiquidRazor\DtoApiBundle\Controller;

use JsonException;
use LiquidRazor\DtoApiBundle\OpenApi\OpenApiBuilder;
use ReflectionException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
final readonly class OpenApiController
{
    public function __construct(private OpenApiBuilder $builder) {}

    /**
     * @throws ReflectionException
     * @throws JsonException
     */
    #[Route(path: '/_schema/openapi.json', name: 'openapi_json', methods: ['GET'])]
    public function json(): Response
    {
        $doc = $this->builder->build();
        return new JsonResponse($doc, 200, ['Content-Type' => 'application/json']);
    }
}
