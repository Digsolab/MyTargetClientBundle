<?php

namespace DSL\MyTargetClientBundle\DependencyInjection;

use chobie\Jira\Api\Authentication\Anonymous;
use chobie\Jira\Api\Authentication\Basic;
use GuzzleHttp\Psr7\Uri;
use MyTarget\Token\ClientCredentials\Credentials;
use MyTarget\Transport\Middleware\HttpMiddlewareStackPrototype;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\DependencyInjection\ConfigurableExtension;
use Symfony\Component\DependencyInjection\Loader;

class MyTargetClientExtension extends ConfigurableExtension
{
    /**
     * @var Loader\XmlFileLoader
     */
    private $loader;

    protected function loadInternal(array $mergedConfig, ContainerBuilder $container)
    {
        $this->loader = new Loader\XmlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $this->loader->load('services.xml');

        $container->setParameter('dsl.mytarget_client.cache_dir', $mergedConfig['cache_dir']);

        // gathering cache collectors
        $cacheCollectors = [];
        foreach ($container->findTaggedServiceIds('dsl.mytarget_client.cache_provider') as $def => $tags) {
            $cacheCollectors[] = $container->getDefinition($def);
        }
        $container->getDefinition('dsl.my_target_client.cache.chain')->replaceArgument(0, $cacheCollectors);

        $baseUriDef = new Definition(Uri::class, [$mergedConfig['base_uri']]);
        $credentialsDef = new Definition(Credentials::class, [$mergedConfig['auth']['client_id'], $mergedConfig['auth']['client_secret']]);

        $container->getDefinition('dsl.my_target_client.request_factory')
                  ->replaceArgument(0, $baseUriDef);
        $container->getDefinition('dsl.my_target_client.token_acquirer')
                  ->replaceArgument(0, $baseUriDef)
                  ->replaceArgument(2, $credentialsDef);

        $container->getDefinition('dsl.my_target_client.cache_control')
                  ->replaceArgument(0, new Reference($mergedConfig['redis_client_id']));

        // gathering middlewares
        $middlewares = [];
        foreach ($container->findTaggedServiceIds('dsl.mytarget_client.middleware') as $def => $tags) {
            $middlewares[] = $container->getDefinition($def);
        }
        $middlewareStack = (new Definition())->setFactory(HttpMiddlewareStackPrototype::class.'::fromArray')
                                             ->setArguments([$middlewares, $container->getDefinition('dsl.my_target_client.transport.http')]);
        $container->getDefinition('dsl.my_target_client.service.client')->replaceArgument(1, $middlewareStack);
    }
}