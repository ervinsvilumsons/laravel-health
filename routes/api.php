<?php

use ErvinsVilumsons\LaravelHealth\Http\Controllers\HealthController;
use ErvinsVilumsons\LaravelHealth\Http\Middleware\Throttle;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Route;

Route::prefix('api')
    ->middleware(Throttle::class)
    ->get(Config::string('health-manager.route.path'), HealthController::class)
    ->name(Config::string('health-manager.route.name'));
