# MyTarget API client bundle
This bundle provides easy setup for [MyTarget SDK client](https://github.com/Digsolab/mytarget-php-ads-sdk) in symfony framework.

## Install using composer
Since the bundle and SDK are not stable it's reasonable to use dev-master revisions. The stable version is coming soon.
`composer require dsl/my-target-client-bundle:dev-master`
`composer require dsl/my-target-sdk:dev-master`
Add `\DSL\MyTargetClientBundle\DslMyTargetClientBundle` to your symfony AppKernel.
```
class AppKernel extends Kernel
{
...
    public function registerBundles()
    {
        ...
        $bundles = [ ... , new \DSL\MyTargetClientBundle\DslMyTargetClientBundle(), ];
        ...
        return $bundles;
    }
...
}
```

## Configure Symfony bundle
In the example below 2 separate clients are configured:
```
dsl_my_target_client:
    redis_lock_client: acme.bundle.service.predis_client        #id of predis client service. Must be configured in your app to store tokens.
    redis_cache_client: acme.bundle.service.predis_client       #id of predis client service. Must be configured in your app to store cache. Can be the same as previous.
    lock_prefix: lock_                                          #keys prefix for reddis
    lock_lifetime: 300                                          #lifetime for token lock. default value is 300
    clients:
        test:
            auth:
                client_id: 12                                   #your mytarget client id
                client_secret: guewhirjwoerwwerwer              #your mytarget client secret
            guzzle_client: acme.bundle.service.guzzle_client    #custom guzzle client. not required
        prod:
            auth:
                client_id: 11
                client_secret: dffwerfqrfqrefqwe
```

## Usage
With the config above you can use two services:
`dsl.my_target_client.service.client.test`
`dsl.my_target_client.service.client.prod`

```php
<?php 
...
    $mtClient =$this->getContainer()->get('dsl.my_target_client.service.client.test');
    $mtMapper =$this->getContainer()->get('dsl.my_target_client.service.mapper');
    $bannerOperator = new BannerOperator($mtClient, $mtMapper);
    var_dump( $bannerOperator->all() );
...
```