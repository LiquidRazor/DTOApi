<?php

namespace LiquidRazor\DtoApiBundle\Resolver;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;

class DtoApiRequestResolver implements ValueResolverInterface
{
    public function resolve(Request $request, ArgumentMetadata $argument): iterable
    {
        $dto = $request->attributes->get('_dtoapi.request_dto');
        if (!$dto) {
            return [];
        }

        // Only resolve for a controller argument whose type matches the DTO class
        $type = $argument->getType();
        if (!$type || !is_object($dto) || !is_a($dto, $type)) {
            return [];
        }

        yield $dto;
    }
}