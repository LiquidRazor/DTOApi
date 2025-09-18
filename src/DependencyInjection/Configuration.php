<?php

namespace LiquidRazor\DtoApiBundle\DependencyInjection;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

final class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('liquidrazor_dto_api');
        $root = $treeBuilder->getRootNode();

        $root
            ->children()
            ->integerNode('normalizer_priority')->defaultValue(10)->min(-255)->max(255)->end()
            ->booleanNode('strict_types')->defaultTrue()->end()
            ->arrayNode('default_responses')
            ->info('Globally applied responses if not explicitly declared')
            ->useAttributeAsKey('status')
            ->arrayPrototype()
            ->children()
            ->scalarNode('class')->isRequired()->end()
            ->scalarNode('description')->defaultNull()->end()
            ->scalarNode('contentType')->defaultNull()->end()
            ->booleanNode('stream')->defaultFalse()->end()
            ->end()
            ->end()
            ->end()
            ->end();


        return $treeBuilder;
    }
}