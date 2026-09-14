<?php

declare(strict_types=1);

namespace ErvinsVilumsons\LaravelHealth;

use ErvinsVilumsons\LaravelHealth\Commands\PruneRateLimitsCommand;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schedule;
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

        if ($this->app->runningInConsole()) {
            $this->commands([
                PruneRateLimitsCommand::class,
            ]);

            if (Config::boolean('health-manager.schedule.prune_rate_limits', true)) {
                Schedule::command(PruneRateLimitsCommand::class)
                    ->hourly()
                    ->withoutOverlapping(60)
                    ->onOneServer();
            }
        }

        $this->publishes([
            __DIR__.'/../config/health-manager.php' => config_path('health-manager.php'),
            __DIR__.'/../stubs/HandleFailedJob.stub' => app_path('Listeners/HandleFailedJob.php'),
            __DIR__.'/../stubs/HandleFailedService.stub' => app_path('Listeners/HandleFailedService.php'),
        ], 'health-manager');
    }
}
