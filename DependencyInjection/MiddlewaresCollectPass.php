<?php

namespace DSL\MyTargetClientBundle\DependencyInjection;

use Dsl\MyTarget\Token\TokenGrantMiddleware;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

class MiddlewaresCollectPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        $transportDef = $container->getDefinition('dsl.my_target_client.transport.http');
        // gathering middlewares TODO move to compiles pass
        $middlewares = [];

        foreach ($container->findTaggedServiceIds('dsl.mytarget_client.middleware') as $def => $tags) {
            $middlewares[] = $container->getDefinition($def);
        }
        $middlewares[] = new Definition(TokenGrantMiddleware::class, [$tokenManagerDef]);

        $middlewareStack = (new Definition())
            ->setFactory(HttpMiddlewareStackPrototype::class . '::fromArray')
            ->setArguments([$middlewares, $transportDef]);
    }
}
