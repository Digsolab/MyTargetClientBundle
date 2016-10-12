<?php

namespace DSL\MyTargetClientBundle\DependencyInjection;

use Dsl\MyTarget\Client;
use Dsl\MyTarget\Token\TokenGrantMiddleware;
use Dsl\MyTarget\Transport\Middleware\HttpMiddlewareStackPrototype;
use DSL\MyTargetClientBundle\DependencyInjection\DslMyTargetClientExtension as Ext;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

class MiddlewaresCollectPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        $config = $container->getExtensionConfig('dsl_my_target');

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

        foreach ($container->findTaggedServiceIds(Ext::PREF . 'client') as $def => $tags) {
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
            $transportDef = $tokenAquirerDef->getArgument(1);

            $allMiddlewares = $middlewares['all'];
            if (array_key_exists($clientName, $middlewares)) {
                $allMiddlewares = array_merge($allMiddlewares, $middlewares[$clientName]);
            }
            if ($config['clients'][$clientName]['token_grant']) {
                $allMiddlewares[] = new Definition(TokenGrantMiddleware::class, [$tokenManagerDef]);
            }
            $middlewareStack = (new Definition())
                ->setFactory(HttpMiddlewareStackPrototype::class . '::fromArray')
                ->setArguments([$allMiddlewares, $transportDef]);
            $client->replaceArgument(1, $middlewareStack);
        }
    }
}
