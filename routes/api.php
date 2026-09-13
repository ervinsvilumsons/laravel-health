<?php

use ErvinsVilumsons\LaravelHealth\Http\Controllers\HealthController;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Route;

Route::prefix('api')
    ->get(Config::string('health-manager.route.path'), HealthController::class)
    ->name(Config::string('health-manager.route.name'));
