<?php
declare(strict_types=1);


namespace LiquidRazor\DtoApiBundle\DependencyInjection;


use Exception;
use LiquidRazor\DtoApiBundle\Response\ErrorResponse;
use LiquidRazor\DtoApiBundle\Response\ValidationErrorResponse;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\Config\FileLocator;


final class DtoApiExtension extends Extension
{
    /**
     * @throws Exception
     */
    public function load(array $configs, ContainerBuilder $container): void
    {
        $processor = new Processor();
        $configuration = new Configuration();
        $config = $processor->processConfiguration($configuration, $configs);


        $container->setParameter('liquidrazor_dto_api.normalizer_priority', (int) $config['normalizer_priority']);
        $container->setParameter('liquidrazor_dto_api.strict_types', (bool) $config['strict_types']);

        $defaults = $config['default_responses'] ?? [
            422 => ['class' => ValidationErrorResponse::class],
            500 => ['class' => ErrorResponse::class],
        ];
        $container->setParameter('liquidrazor_dto_api.default_responses', $defaults);


        $loader = new PhpFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('services.php');
    }
}