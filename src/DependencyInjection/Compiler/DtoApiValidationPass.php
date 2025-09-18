<?php

declare(strict_types=1);

namespace LiquidRazor\DtoApiBundle\DependencyInjection\Compiler;

use LiquidRazor\DtoApiBundle\Validation\DtoApiConstraintLoader;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

final class DtoApiValidationPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition('validator.builder')) {
            return;
        }

        // Ensure our loader service exists
        if (!$container->hasDefinition(DtoApiConstraintLoader::class)) {
            return;
        }

        $builderDef = $container->getDefinition('validator.builder');
        // Register our loader with the builder
        $builderDef->addMethodCall('addLoader', [new Reference(DtoApiConstraintLoader::class)]);
    }
}
