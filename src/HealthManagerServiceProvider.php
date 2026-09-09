<?php

declare(strict_types=1);

namespace ErvinsVilumsons\LaravelHealth;

use Illuminate\Support\ServiceProvider;

class HealthManagerServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/health-manager.php', 'health-manager');
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../routes/api.php');

        $this->publishes([__DIR__.'/../config/health-manager.php' => config_path('health-manager.php')], 'health-manager');

        $this->publishes([__DIR__.'/../stubs/HandleFailedJob.stub' => app_path('Listeners/HandleFailedJob.php')], 'health-manager');
        $this->publishes([__DIR__.'/../stubs/HandleFailedService.stub' => app_path('Listeners/HandleFailedService.php')], 'health-manager');
    }
}
