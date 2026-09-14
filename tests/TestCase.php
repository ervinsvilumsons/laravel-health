<?php

namespace ErvinsVilumsons\LaravelHealth\Tests;

use ErvinsVilumsons\LaravelHealth\HealthManagerServiceProvider;
use Illuminate\Contracts\Config\Repository;
use Orchestra\Testbench\TestCase as OrchestraTestCase;

abstract class TestCase extends OrchestraTestCase
{
    protected function defineEnvironment($app): void
    {
        /** @var Repository $config */
        $config = $app['config'];
        $token = getenv('TEST_TOKEN');

        $config->set(
            'health-manager.throttle.path',
            storage_path("framework/health-rate-limit-test-{$token}"),
        );
    }

    protected function getPackageProviders($app): array
    {
        return [HealthManagerServiceProvider::class];
    }
}
