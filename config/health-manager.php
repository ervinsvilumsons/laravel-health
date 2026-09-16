<?php

use ErvinsVilumsons\LaravelHealth\Services\CacheService;
use ErvinsVilumsons\LaravelHealth\Services\DatabaseService;
use ErvinsVilumsons\LaravelHealth\Services\MailService;
use ErvinsVilumsons\LaravelHealth\Services\QueueService;
use ErvinsVilumsons\LaravelHealth\Services\RedisService;

return [

    'route' => [
        'path' => env('HEALTH_PATH', '/health'),
        'name' => 'health.check',
    ],

    'throttle' => [
        'max_attempts' => 30,
        'decay_seconds' => 60,
        'path' => storage_path('framework/cache/health-rate-limit'),
    ],

    'event' => [
        'title' => 'Service Alert',
        'message' => 'Following services are down:',
        'level' => 'error',
    ],

    'response' => [
        'service_timeout' => 1,
        'include_details' => env('HEALTH_DEBUG', false),
    ],

    'schedule' => [
        'prune_rate_limits' => true,
    ],

    'services' => [

        'cache' => [
            'enabled' => true,
            'connection' => env('CACHE_STORE', 'database'),
            'dependency' => 'database',
            'class' => CacheService::class,
        ],

        'database' => [
            'enabled' => true,
            'connection' => env('DB_CONNECTION', 'sqlite'),
            'class' => DatabaseService::class,
        ],

        'mail' => [
            'enabled' => false,
            'connection' => env('MAIL_MAILER', 'log'),
            'class' => MailService::class,
        ],

        'queue' => [
            'enabled' => false,
            'connection' => env('QUEUE_CONNECTION', 'database'),
            'class' => QueueService::class,
        ],

        'redis' => [
            'enabled' => false,
            'connection' => env('REDIS_CLIENT', 'phpredis'),
            'class' => RedisService::class,
        ],

    ],

];
