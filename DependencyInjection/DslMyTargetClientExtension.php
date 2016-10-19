<?php

namespace DSL\MyTargetClientBundle\DependencyInjection;

use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\Instantiator\Instantiator;
use DSL\Lock\RedisLock;
use Dsl\MyTarget\Transport\Middleware\HttpMiddlewareStackPrototype;
use GuzzleHttp\Psr7\Uri;
use Dsl\MyTarget\Client;
use Dsl\MyTarget\Token\ClientCredentials\Credentials;
use Dsl\MyTarget\Token\TokenAcquirer;
use Dsl\MyTarget\Token\TokenManager;
use Dsl\MyTarget\Transport\HttpTransport;
use Dsl\MyTarget\Transport\RequestFactory;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\DependencyInjection\ConfigurableExtension;
use Symfony\Component\DependencyInjection\Loader;

class DslMyTargetClientExtension extends ConfigurableExtension
{

    const CLIENT_DEF_TEMPLATE = 'dsl.my_target_client.service.client.%s';
    const TOKEN_MANAGER_DEF_TEMPLATE = 'dsl.my_target_client.service.token_manager.%s';
    const PREF = 'dsl.my_target_client.';
    /**
     * @var Loader\XmlFileLoader
     */
    private $loader;

    protected function loadInternal(array $mergedConfig, ContainerBuilder $container)
    {
        $this->loader = new Loader\XmlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $this->loader->load('services.xml');

        $redisRef = new Reference($mergedConfig['redis_lock_client']);
        $lockDef = new Definition(RedisLock::class, [$redisRef]);

        $redisRef = new Reference($mergedConfig['redis_token_client']);
        $container->getDefinition(self::PREF . 'cache_control')
                  ->replaceArgument(0, $redisRef);

        $lockManagerDef = $container->getDefinition(self::PREF . 'lock_manager')
                                    ->replaceArgument(0, $lockDef)
                                    ->replaceArgument(1, $mergedConfig['lock_lifetime'])
                                    ->replaceArgument(2, $mergedConfig['lock_prefix']);

        $this->loadTypes($container);
        $container->getDefinition(self::PREF . 'token_storage')->replaceArgument(1, $mergedConfig['token_prefix']);

        foreach ($mergedConfig['clients'] as $name => $config) {
            $this->loadClient($name, $config, $lockManagerDef, $container);
        }

        try {
            $name = sprintf(self::CLIENT_DEF_TEMPLATE, $mergedConfig['default_client']);
            $container->getDefinition($name);
            $container->setAlias(self::PREF . 'client', $name);
            $name = sprintf(self::TOKEN_MANAGER_DEF_TEMPLATE, $mergedConfig['default_client']);
            $container->setAlias(self::PREF . 'token_manager', $name);
        } catch (ServiceNotFoundException $e) {
        }

    }

    protected function loadTypes(ContainerBuilder $container)
    {
        $objectTypeDef = $container->getDefinition(self::PREF . 'type.object');
        $readerDef = new Definition(AnnotationReader::class);
        $instantiatorDef = new Definition(Instantiator::class);
        $objectTypeDef->setArguments([$readerDef, $instantiatorDef]);

        $types = [];
        foreach ($container->findTaggedServiceIds(self::PREF . 'type') as $def => $tags) {
            foreach ($tags as $attributes) {
                $types[$attributes['type']] = $container->getDefinition($def);
            }
        }
        $container->getDefinition(self::PREF . 'service.mapper')->replaceArgument(0, $types);
    }

    /**
     * @param array            $mergedConfig
     * @param ContainerBuilder $container
     *
     * @throws \Symfony\Component\DependencyInjection\Exception\BadMethodCallException
     * @throws \Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException
     * @throws \Symfony\Component\DependencyInjection\Exception\OutOfBoundsException
     */
    protected function loadClient($clientName, array $mergedConfig, $lockManagerDef, ContainerBuilder $container)
    {
        $container->setParameter(self::PREF . 'cache_dir', $mergedConfig['cache_dir']);

        $baseUriDef = new Definition(Uri::class, [$mergedConfig['base_uri']]);
        $credentialsDef = new Definition(
            Credentials::class,
            [$mergedConfig['auth']['client_id'], $mergedConfig['auth']['client_secret']]
        );
        $requestFactoryDef = new Definition(RequestFactory::class, [$baseUriDef]);
        $middlewareStack = new Definition(HttpMiddlewareStackPrototype::class);

        $tokenAcquirerDef = new Definition(TokenAcquirer::class, [$baseUriDef, $middlewareStack, $credentialsDef]);
        $tokenManagerDef = new Definition(
            TokenManager::class,
            [
                $tokenAcquirerDef,
                $container->getDefinition(self::PREF . 'token_storage'),
                $credentialsDef,
                $lockManagerDef,
            ]
        );

        $container->addDefinitions(
            [
                $requestFactoryDef,
                $tokenAcquirerDef,
                sprintf(self::TOKEN_MANAGER_DEF_TEMPLATE, $clientName) => $tokenManagerDef,
            ]
        );

        $clientDefinition = new Definition(Client::class, [$requestFactoryDef, null]);
        $clientDefinition->addTag(self::PREF . 'client', ['name' => $clientName]);
        $container->setDefinition(sprintf(self::CLIENT_DEF_TEMPLATE, $clientName), $clientDefinition);
    }
}