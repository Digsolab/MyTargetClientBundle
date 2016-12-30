# MyTarget API client bundle
This bundle provides easy setup for [MyTarget SDK client](https://github.com/Digsolab/mytarget-php-ads-sdk) in symfony framework.

## Install using composer
Since the bundle and SDK are not stable it's reasonable to use dev-master revisions. The stable version is coming soon.
`composer require dsl/my-target-client-bundle:~0.4.0`
Add `\DSL\MyTargetClientBundle\DslMyTargetClientBundle` to your symfony AppKernel.
```php
<?php
class AppKernel extends Kernel
{
//...
    public function registerBundles()
    {
        ...
        $bundles = [ ... , new \DSL\MyTargetClientBundle\DslMyTargetClientBundle(), ];
        ...
        return $bundles;
    }
//...
}
```

## Configure Symfony bundle
In the example below 2 separate clients are configured:
```
dsl_my_target_client:
    redis_lock_client: acme.bundle.service.predis_client            #id of predis client service. Must be configured in your app to store locks.
    redis_token_client: acme.bundle.service.predis_client           #id of predis client service. Must be configured in your app to store tokens cache. Can be the same as previous.
    lock_prefix: lock_                                              #keys prefix for reddis
    lock_lifetime: 300                                              #lifetime for token lock. default value is 300
    default_client: test
    clients:
        main:
            auth:
                client_id: 12                                       #your mytarget client id
                client_secret: someclientsecret                     #your mytarget client secret
            guzzle_client: acme.bundle.service.guzzle_client        #custom guzzle client. not required
        test:
            auth:
                client_id: 49
                client_secret: someotherclientsecret
            transport_service: acme.bundle.service.guzzle_client    #custom http transport. not required
```

## Usage
With the config above you can use two services:

`dsl.my_target_client.service.client.test`
`dsl.my_target_client.service.client.main`

Also, the bundle creates the alias `dsl.mytarget_client.client` for a service, specified in the `default_client` parameter.
In this example the alias `dsl.mytarget_client.client` points to `dsl.my_target_client.service.client.test` but by default the `default_client` parameter is equals `main`. So if you omit this parameter, alias will point to `dsl.my_target_client.service.client.main`. 

```php
<?php 
//...
    $mtClient =$this->getContainer()->get('dsl.my_target_client.client'); // dsl.my_target_client.service.client.test
    // or $mtClient =$this->getContainer()->get('dsl.my_target_client.service.client.main');
    $mtMapper =$this->getContainer()->get('dsl.my_target_client.service.mapper');
    $bannerOperator = new BannerOperator($mtClient, $mtMapper);
    var_dump( $bannerOperator->all() );
//...
```

## Middlewares

You can create your own middlewares and mark them with a tag `<tag name="dsl.my_target_client.middleware" radius="128" client="client-name" />`.  
If you specify `client` attribute this middleware will be added to this client only, otherwise all clients will receive it.  
By default all middlewares get the radius of `PHP_INT_MAX`, you can specify any other desirable value in the range from `PHP_INT_MIN` to `PHP_INT_MAX`. You can think of a middleware as a spherical layer and the radius is a proximity of the middleware to the centre of the sphere, where `HttpTransport` is at the centre.
