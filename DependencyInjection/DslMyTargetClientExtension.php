<?php

namespace DSL\MyTargetClientBundle\DependencyInjection;

use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\Instantiator\Instantiator;
use DSL\Lock\RedisLock;
use Dsl\MyTarget\Token\TokenGrantMiddleware;
use GuzzleHttp\Psr7\Uri;
use Dsl\MyTarget\Client;
use Dsl\MyTarget\Token\ClientCredentials\Credentials;
use Dsl\MyTarget\Token\TokenAcquirer;
use Dsl\MyTarget\Token\TokenManager;
use Dsl\MyTarget\Transport\HttpTransport;
use Dsl\MyTarget\Transport\Middleware\HttpMiddlewareStackPrototype;
use Dsl\MyTarget\Transport\RequestFactory;
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

        $redisRef = new Reference($mergedConfig['redis_client']);
        $lockDef = new Definition(RedisLock::class, [$redisRef]);
        $lockManagerDef = $container->getDefinition('dsl.my_target_client.lock_manager')
            ->replaceArgument(0, $lockDef)
            ->replaceArgument(1, $mergedConfig['lock_lifetime'])
            ->replaceArgument(2, $mergedConfig['lock_prefix']);

        $this->loadTypes($container);
        $types = [];
        foreach ($container->findTaggedServiceIds('dsl.my_target_client.type') as $def => $tags) {
            foreach ($tags as $attributes) {
                $types[$attributes['type']] = $container->getDefinition($def);
            }
        }
        $container->getDefinition('dsl.my_target_client.service.mapper')->replaceArgument(0, $types);
        $container->getDefinition('dsl.my_target_client.token_storage')->replaceArgument(
            1,
            $mergedConfig['token_prefix']
        );

        foreach ($mergedConfig['clients'] as $name => $config) {
            $this->loadClient($name, $config, $redisRef, $lockManagerDef, $container);
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
     * @param array            $mergedConfig
     * @param ContainerBuilder $container
     *
     * @throws \Symfony\Component\DependencyInjection\Exception\BadMethodCallException
     * @throws \Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException
     * @throws \Symfony\Component\DependencyInjection\Exception\OutOfBoundsException
     */
    protected function loadClient(
        $clientName,
        array $mergedConfig,
        $redisRef,
        $lockManagerDef,
        ContainerBuilder $container
    ) {
        if ($mergedConfig['guzzle_client'] !== null) {
            $transportDef = new Definition(HttpTransport::class, [new Reference($mergedConfig['guzzle_client'])]);
        } else {
            if (null !== $mergedConfig['transport_service']) {
                $transportDef = new Reference($mergedConfig['transport_service']);
            } else {
                $transportDef = $container->getDefinition('dsl.my_target_client.transport.http');
            }
        }

        $container->setParameter('dsl.mytarget_client.cache_dir', $mergedConfig['cache_dir']);

        $baseUriDef = new Definition(Uri::class, [$mergedConfig['base_uri']]);
        $credentialsDef = new Definition(
            Credentials::class,
            [$mergedConfig['auth']['client_id'], $mergedConfig['auth']['client_secret']]
        );
        $requestFactoryDef = new Definition(RequestFactory::class, [$baseUriDef]);
        $tokenAcquirerDef = new Definition(TokenAcquirer::class, [$baseUriDef, $transportDef, $credentialsDef]);
        $tokenManagerDef = new Definition(
            TokenManager::class,
            [
                $tokenAcquirerDef,
                $container->getDefinition('dsl.my_target_client.token_storage'),
                $lockManagerDef,
            ]
        );

        $container->addDefinitions(
            [
                $requestFactoryDef,
                $tokenAcquirerDef,
                'dsl.my_target_client.service.token_manager.' . $clientName => $tokenManagerDef
            ]
        );
        
        $container->getDefinition('dsl.my_target_client.cache_control')
                  ->replaceArgument(0, $redisRef);

        // gathering middlewares TODO move to compiles pass
        $middlewares = [];

        foreach ($container->findTaggedServiceIds('dsl.mytarget_client.middleware') as $def => $tags) {
            $middlewares[] = $container->getDefinition($def);
        }
        $middlewares[] = new Definition(TokenGrantMiddleware::class, [$tokenManagerDef]);

        $middlewareStack = (new Definition())
            ->setFactory(HttpMiddlewareStackPrototype::class . '::fromArray')
            ->setArguments([$middlewares, $transportDef]);

        $clientDefinition = new Definition(Client::class, [$requestFactoryDef, $middlewareStack]);
        $container->setDefinition('dsl.my_target_client.service.client.' . $clientName, $clientDefinition);
    }
}