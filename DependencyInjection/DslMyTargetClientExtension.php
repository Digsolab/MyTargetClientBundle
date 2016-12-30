<?php

namespace DSL\MyTargetClientBundle\DependencyInjection;

use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\Instantiator\Instantiator;
use DSL\Lock\RedisLock;
use Dsl\MyTarget\Token\TokenGrantMiddleware;
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
    const TRANSPORT_TEMPLATE = 'dsl.my_target_client.transport.%s';
    const HTTP_STACK_TEMPLATE = 'dsl.my_target_client.http_stack.%s';
    const CLIENT_PREFIX = 'dsl.my_target_client.service.client.';
    const TOKEN_MANAGER_DEF_TEMPLATE = 'dsl.my_target_client.service.token_manager.%s';
    const TOKEN_MIDDLEWARE_TEMPLATE = 'dsl.my_target_client.token_middleware.%s';
    const PREF = 'dsl.my_target_client.';
    /**
     * @var Loader\XmlFileLoader
     */
    private $loader;

    protected function loadInternal(array $mergedConfig, ContainerBuilder $container)
    {
        $this->loader = new Loader\XmlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $this->loader->load('services.xml');

        $lockDef = new Definition(RedisLock::class, [new Reference($mergedConfig['redis_lock_client'])]);

        $container->getDefinition(self::PREF . 'cache_control')
                  ->replaceArgument(0, new Reference($mergedConfig['redis_token_client']));

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
            $name = self::CLIENT_PREFIX . $mergedConfig['default_client'];
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
        foreach ($container->findTaggedServiceIds(self::PREF . 'type') as $serviceId => $tags) {
            foreach ($tags as $attributes) {
                $types[$attributes['type']] = new Reference($serviceId);
            }
        }
        $container->getDefinition(self::PREF . 'service.mapper')->replaceArgument(0, $types);
    }

    protected function loadClient($clientName, array $mergedConfig, $lockManagerDef, ContainerBuilder $container)
    {
        $transportId = sprintf(self::TRANSPORT_TEMPLATE, $clientName);
        $transport = new Reference($transportId);

        if ($mergedConfig['guzzle_client'] !== null) {
            $container->setDefinition($transportId, new Definition(HttpTransport::class, [new Reference($mergedConfig['guzzle_client'])]));
        } else {
            if (null !== $mergedConfig['transport_service']) {
                $container->setAlias($transportId, $mergedConfig['transport_service']);
            } else {
                $container->setAlias($transportId, self::PREF . 'transport.http');
            }
        }

        $container->setParameter(self::PREF . 'cache_dir', $mergedConfig['cache_dir']);

        $baseUriDef = new Definition(Uri::class, [$mergedConfig['base_uri']]);
        $credentialsDef = new Definition(
            Credentials::class,
            [$mergedConfig['auth']['client_id'], $mergedConfig['auth']['client_secret']]
        );

        if ($mergedConfig["token_grant"]) {
            $tokenAcquirerDef = new Definition(TokenAcquirer::class, [$baseUriDef, $transport, $credentialsDef]);
            $tokenManagerDef = new Definition(TokenManager::class, [
                $tokenAcquirerDef,
                new Reference(self::PREF . 'token_storage'),
                $credentialsDef,
                $lockManagerDef,
            ]);

            if ($mergedConfig["token_grant_logger"] !== null) {
                $tokenManagerDef->addMethodCall("setLogger", [new Reference($mergedConfig["token_grant_logger"])]);
            }

            $container->setDefinition($managerId = sprintf(self::TOKEN_MANAGER_DEF_TEMPLATE, $clientName), $tokenManagerDef);

            $tokenMiddleware = new Definition(TokenGrantMiddleware::class, [new Reference($managerId)]);
            $tokenMiddleware->addTag(self::PREF . "middleware", ["client" => $clientName, "radius" => 8192]);

            $container->setDefinition(sprintf(self::TOKEN_MIDDLEWARE_TEMPLATE, $clientName), $tokenMiddleware);
        }

        $httpStack = new Definition(HttpMiddlewareStackPrototype::class, [$transport]);
        $httpStack->setFactory([HttpMiddlewareStackPrototype::class, "newEmpty"]);
        $container->setDefinition($httpStackId = sprintf(self::HTTP_STACK_TEMPLATE, $clientName), $httpStack);

        $clientDefinition = new Definition(Client::class, [new Definition(RequestFactory::class, [$baseUriDef]), new Reference($httpStackId)]);

        $container->setDefinition(self::CLIENT_PREFIX . $clientName, $clientDefinition);
    }
}
