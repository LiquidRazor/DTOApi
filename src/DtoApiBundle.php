<?php

declare(strict_types=1);

namespace LiquidRazor\DtoApiBundle;

use LiquidRazor\DtoApiBundle\DependencyInjection\Compiler\DtoApiValidationPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

final class DtoApiBundle extends Bundle
{
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);
        $container->addCompilerPass(new DtoApiValidationPass());
    }

}