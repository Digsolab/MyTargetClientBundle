<?php

namespace DSL\MyTargetClientBundle;

use DSL\LockInterface;
use Dsl\MyTarget\Token\LockManager;

class PrefixLockManager extends LockManager
{
    public function __construct(LockInterface $lock, $lifetime, $prefix)
    {
        $hashFunction = function ($v) use ($prefix) {
            return $prefix . $v;
        };
        parent::__construct($lock, $lifetime, $hashFunction);
    }
}
