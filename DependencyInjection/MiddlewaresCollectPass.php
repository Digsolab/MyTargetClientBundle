<?php

namespace DSL\MyTargetClientBundle\DependencyInjection;

use Dsl\MyTarget\Client;
use Dsl\MyTarget\Token\TokenGrantMiddleware;
use Dsl\MyTarget\Transport\GuzzleHttpTransport;
use Dsl\MyTarget\Transport\Middleware\HttpMiddlewareStackPrototype;
use DSL\MyTargetClientBundle\DependencyInjection\DslMyTargetClientExtension as Ext;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

class MiddlewaresCollectPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        $config = $container->getExtensionConfig('dsl_my_target_client');
        $config = array_pop($config);

        $middlewares = [];
        foreach ($container->findTaggedServiceIds(Ext::PREF . 'middleware') as $def => $tags) {
            foreach ($tags as $tag) {
                if (array_key_exists('client', $tag)) {
                    $middlewares[$tag['client']][$def] = $container->getDefinition($def);
                } else {
                    $middlewares['all'][$def] = $container->getDefinition($def);
                }
            }
        }

        $tagged = $container->findTaggedServiceIds(Ext::PREF . 'client');
        foreach ($tagged as $def => $tags) {
            if (isset($config['guzzle_client']) && null !== $config) {
                $transportDef = new Definition(GuzzleHttpTransport::class, [new Reference($config['guzzle_client'])]);
            } else {
                $transportDef = $container->getDefinition(Ext::PREF . 'transport.http');
            }

            $client = $container->getDefinition($def);
            if (Client::class !== $client->getClass()) {
                continue;
            }
            if ( ! isset($tags[0]['name'])) {
                continue;
            }
            $clientName = $tags[0]['name'];

            $tokenManagerDef = $container->getDefinition(sprintf(Ext::TOKEN_MANAGER_DEF_TEMPLATE, $clientName));
            $tokenAquirerDef = $tokenManagerDef->getArgument(0);

            // To prevent cyclic references, TokenAcquirer has separate MW Stack
            /** @var Definition $tokensMiddlewareStack */
            $tokensMiddlewareStack = $tokenAquirerDef->getArgument(1);
            $middlewareStack = new Definition(HttpMiddlewareStackPrototype::class);

            $allMiddlewares = $middlewares['all'];
            if (array_key_exists($clientName, $middlewares)) {
                $allMiddlewares = array_merge($allMiddlewares, $middlewares[$clientName]);
            }
            $clientConfig = $config['clients'][$clientName];
            $tokensMiddlewareStack
                ->setFactory(HttpMiddlewareStackPrototype::class . '::fromArray')
                ->setArguments([$allMiddlewares, $transportDef]);

            // TokenAcquirer doesn't need TokenGrantMiddleware
            if (!isset($clientConfig['token_grant']) || true === $clientConfig['token_grant']) {
                array_unshift($allMiddlewares, new Definition(TokenGrantMiddleware::class, [$tokenManagerDef]));
            }
            $middlewareStack
                ->setFactory(HttpMiddlewareStackPrototype::class . '::fromArray')
                ->setArguments([$allMiddlewares, $transportDef]);
            $client->replaceArgument(1, $middlewareStack);
        }
    }
}
