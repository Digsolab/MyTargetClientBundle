<?php

namespace DSL\MyTargetClientBundle;

use Doctrine\Common\Cache\Cache;
use Dsl\MyTarget\Token\DoctrineCacheTokenStorage;

class PrefixDoctrineCacheTokenStorage extends DoctrineCacheTokenStorage
{
    public function __construct(Cache $cache, $prefix)
    {
        $hashFunction = function ($v) use ($prefix) {
            return $prefix . $v;
        };
        parent::__construct($cache, $hashFunction);
    }

}
