<?php

namespace BayCMS\Auth;

abstract class Auth {
    protected \BayCMS\Base\BayCMSContext $context;
    public function __construct(\BayCMS\Base\BayCMSContext $context){
        $this->context=$context;
    }

    abstract public function createUser($user, $pw): bool|array;

    abstract public function createAccess($user, $pw): bool|array;

}