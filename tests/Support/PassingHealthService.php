<?php

namespace ErvinsVilumsons\LaravelHealth\Tests\Support;

class PassingHealthService extends TestHealthService
{
    public function __construct()
    {
        parent::__construct('Alpha');
    }
}
