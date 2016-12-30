<?php

namespace DSL\MyTargetClientBundle\DependencyInjection;

use Dsl\MyTarget\Client;
use Dsl\MyTarget\Token\TokenGrantMiddleware;
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
        $middlewaresPerClient = [];
        $middlewaresAnyClient = [];
        foreach ($container->findTaggedServiceIds(Ext::PREF . 'middleware') as $serviceId => $tags) {
            foreach ($tags as $tag) {
                $radius = isset($tag['radius']) ? (int)$tag['radius'] : 0;

                if (isset($tag['client'])) {
                    $middlewaresPerClient[$tag['client']][$serviceId] = [$radius, new Reference($serviceId)];
                } else {
                    $middlewaresAnyClient[$serviceId] = [$radius, new Reference($serviceId)];
                }
            }
        }
        $comparator = function ($l, $r) {
            return $l[0] - $r[0];
        };

        // we need to get only unique Definitions (otherwise if there are aliases we will add middlewares to them twice)
        $serviceIds = $container->getServiceIds();
        $clients = [];
        $clientDefs = new \SplObjectStorage();
        foreach ($serviceIds as $serviceId) {
            if (strpos($serviceId, Ext::CLIENT_PREFIX) !== 0) {
                continue;
            }
            if ( ! $clientDefs->contains($container->getDefinition($serviceId))) {
                $clients[$serviceId] = substr($serviceId, strlen(Ext::CLIENT_PREFIX));
                $clientDefs->attach($container->getDefinition($serviceId));
            }
        }

        foreach ($clients as $clientServiceId => $clientName) {
            $clientMiddlewares = array_merge(
                $middlewaresAnyClient,
                isset($middlewaresPerClient[$clientName]) ? $middlewaresPerClient[$clientName] : []);
            usort($clientMiddlewares, $comparator);

            $httpStack = $container->getDefinition(sprintf(Ext::HTTP_STACK_TEMPLATE, $clientName));

            foreach ($clientMiddlewares as list($radius, $middleware)) {
                $httpStack->addMethodCall("push", [$middleware]);
            }
        }
    }
}
