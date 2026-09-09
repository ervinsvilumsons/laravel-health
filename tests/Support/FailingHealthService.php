<?php

namespace ErvinsVilumsons\LaravelHealth\Tests\Support;

class FailingHealthService extends TestHealthService
{
    public function __construct()
    {
        parent::__construct('Zulu', true);
    }
}
