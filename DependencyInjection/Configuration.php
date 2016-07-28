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
                ->scalarNode('redis_client')->isRequired()->end()
                ->scalarNode('lock_lifetime')->defaultValue(300)->end()
                ->scalarNode('lock_prefix')->defaultValue('lock')->end()
                ->arrayNode('clients')->useAttributeAsKey('name')
                    ->prototype('array')
                    ->children()
                        ->arrayNode('auth')
                            ->children()
                                ->scalarNode('client_id')->end()
                                ->scalarNode('client_secret')->end()
                            ->end()
                        ->end()
                        ->scalarNode('base_uri')->defaultValue('https://target.my.com/api/')->end()
                        ->scalarNode('cache_dir')->defaultValue('%kernel.root_dir%/cache/mytarget')->end()
                    ->end()
                ->end()
            ->end()
        ;
        return $treeBuilder;
    }

}