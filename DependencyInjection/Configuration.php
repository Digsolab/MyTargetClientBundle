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
                ->scalarNode('token_prefix')->defaultValue('token_')->end()
                ->scalarNode('lock_prefix')->defaultValue('lock_')->end()
                ->scalarNode('lock_lifetime')->defaultValue(300)->end()
                ->arrayNode('clients')
                    ->useAttributeAsKey('name')
                    ->prototype('array')
                        ->children()
                            ->arrayNode('auth')
                                ->children()
                                    ->scalarNode('client_id')->end()
                                    ->scalarNode('client_secret')->end()
                                ->end()
                            ->end()
                            ->scalarNode('base_uri')->defaultValue('https://target.my.com')->end()
                            ->scalarNode('cache_dir')->defaultValue('%kernel.root_dir%/cache/mytarget')->end()
                            ->scalarNode('guzzle_client')->defaultValue(null)->end()
                            ->scalarNode('transport_service')->defaultValue(null)->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;
        return $treeBuilder;
    }

}