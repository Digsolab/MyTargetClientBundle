<?php

namespace DSL\MyTargetClientBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder();
        $root = $treeBuilder->root('dsl_my_target_client');
        $root
            ->children()
                ->arrayNode('auth')
                    ->children()
                        ->scalarNode('client_id')->end()
                        ->scalarNode('client_secret')->end()
                        ->end()
                    ->end()
                ->scalarNode('base_uri')->isRequired()->end()
            ->end()
        ;
        return $treeBuilder;
    }

}