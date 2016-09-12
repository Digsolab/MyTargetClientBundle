<?php

namespace DSL\MyTargetClientBundle;

use DSL\MyTargetClientBundle\DependencyInjection\MiddlewaresCollectPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class DslMyTargetClientBundle extends Bundle
{
    public function build(ContainerBuilder $container)
    {
        parent::build($container);
        $container->addCompilerPass(new MiddlewaresCollectPass());
    }
}
