<?php

namespace ErvinsVilumsons\LaravelHealth\Tests;

use ErvinsVilumsons\LaravelHealth\HealthManagerServiceProvider;
use Orchestra\Testbench\TestCase as OrchestraTestCase;

abstract class TestCase extends OrchestraTestCase
{
    protected function getPackageProviders($app): array
    {
        return [HealthManagerServiceProvider::class];
    }
}
