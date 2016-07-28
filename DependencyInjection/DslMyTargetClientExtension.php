<?php

namespace DSL\MyTargetClientBundle\DependencyInjection;

use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\Instantiator\Instantiator;
use GuzzleHttp\Psr7\Uri;
use MyTarget\Client;
use MyTarget\Token\ClientCredentials\Credentials;
use MyTarget\Transport\Middleware\HttpMiddlewareStackPrototype;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\DependencyInjection\ConfigurableExtension;
use Symfony\Component\DependencyInjection\Loader;

class DslMyTargetClientExtension extends ConfigurableExtension
{
    /**
     * @var Loader\XmlFileLoader
     */
    private $loader;

    protected function loadInternal(array $mergedConfig, ContainerBuilder $container)
    {
        $this->loader = new Loader\XmlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $this->loader->load('services.xml');

        $this->loadTypes($container);
        $types = [];
        foreach ($container->findTaggedServiceIds('dsl.my_target_client.type') as $def => $tags) {
            $types[] = $container->getDefinition($def);
        }
        $container->getDefinition('dsl.my_target_client.service.mapper')->replaceArgument(0, $types);

        foreach ($mergedConfig['clients'] as $name => $config) {
            $this->loadClient($name, $config, $container);
        }
    }

    protected function loadTypes(ContainerBuilder $container)
    {
        $objectTypeDef = $container->getDefinition('dsl.my_target_client.type.object');
        $readerDef = new Definition(AnnotationReader::class);
        $instantiatorDef = new Definition(Instantiator::class);
        $objectTypeDef->setArguments([$readerDef, $instantiatorDef]);
    }

    /**
     * @param array $mergedConfig
     * @param ContainerBuilder $container
     */
    protected function loadClient($clientName, array $mergedConfig, ContainerBuilder $container)
    {
        $container->setParameter('dsl.mytarget_client.cache_dir', $mergedConfig['cache_dir']);

        // gathering cache collectors
        $cacheCollectors = [];
        foreach ($container->findTaggedServiceIds('dsl.mytarget_client.cache_provider') as $def => $tags) {
            $cacheCollectors[] = $container->getDefinition($def);
        }
        $container->getDefinition('dsl.my_target_client.cache.chain')->replaceArgument(0, $cacheCollectors);

        $baseUriDef = new Definition(Uri::class, [$mergedConfig['base_uri']]);
        $credentialsDef = new Definition(
            Credentials::class,
            [$mergedConfig['auth']['client_id'], $mergedConfig['auth']['client_secret']]
        );

        $container->getDefinition('dsl.my_target_client.request_factory')
                  ->replaceArgument(0, $baseUriDef);
        $container->getDefinition('dsl.my_target_client.token_acquirer')
                  ->replaceArgument(0, $baseUriDef)
                  ->replaceArgument(2, $credentialsDef);

        $container->getDefinition('dsl.my_target_client.cache_control')
                  ->replaceArgument(0, new Reference($mergedConfig['redis_client']));

        // gathering middlewares
        $middlewares = [];
        foreach ($container->findTaggedServiceIds('dsl.mytarget_client.middleware') as $def => $tags) {
            $middlewares[] = $container->getDefinition($def);
        }
        $middlewareStack = (new Definition())->setFactory(HttpMiddlewareStackPrototype::class . '::fromArray')
                                             ->setArguments(
                                                 [
                                                     $middlewares,
                                                     $container->getDefinition('dsl.my_target_client.transport.http')
                                                 ]
                                             );
        $requestFactoryDefinition = $container->getDefinition('dsl.my_target_client.request_factory');
        $clientDefinition = new Definition(Client::class, [$requestFactoryDefinition, $middlewareStack]);
        $container->setDefinition('dsl.my_target_client.service.client.'.$clientName, $clientDefinition);
    }
}