# Laravel Health Manager

[![Latest Version on Packagist](https://img.shields.io/packagist/v/ervinsvilumsons/laravel-health.svg?style=flat-square)](https://packagist.org/packages/ervinsvilumsons/laravel-health)
![PHP 8.3+](https://img.shields.io/badge/PHP-8.3%2B-777BB4?logo=php)
![Laravel 10+](https://img.shields.io/badge/Laravel-10%2B-FF2D20?logo=laravel&logoColor=white)
[![Tests](https://github.com/ervinsvilumsons/laravel-health/actions/workflows/ci.yml/badge.svg)](https://github.com/ervinsvilumsons/laravel-health/actions/workflows/ci.yml)
[![codecov](https://codecov.io/github/ervinsvilumsons/laravel-health/branch/staging/graph/badge.svg?token=0F2HQQXZH2)](https://codecov.io/github/ervinsvilumsons/laravel-health)
[![License](https://img.shields.io/github/license/ervinsvilumsons/laravel-health)](https://github.com/ervinsvilumsons/laravel-health/blob/main/LICENSE)

Laravel Health provides a JSON health-check endpoint for Laravel applications. Built-in checks cover cache, database, mail, queue, and Redis connections. 
Checks run concurrently and each service reports `up`, `skipped` or `down` with its response time.

## 📦 Installation

```bash
composer require ervinsvilumsons/laravel-health
```

Publish the package configuration when you need to customize it:

```bash
php artisan vendor:publish --tag=health-manager
```

## 🧩 Built-in Services

The package includes these service classes:

| Service | Configuration connection | Default |
| --- | --- | --- |
| Cache | `CACHE_STORE` | Enabled |
| Database | `DB_CONNECTION` | Enabled |
| Mail | `MAIL_MAILER` | Disabled |
| Queue | `QUEUE_CONNECTION` | Disabled |
| Redis | `REDIS_CLIENT` | Disabled |

Enable a built-in service in `config/health-manager.php`:

```php
'queue' => [
    'enabled' => true,
    'connection' => env('QUEUE_CONNECTION', 'database'),
    'class' => QueueService::class,
],
```

## 🚀 Quick Start

The package registers its service provider through Laravel package discovery. The health endpoint is available at:

```text
GET /health
```

The default response uses [JSON:API-style](https://jsonapi.org/) `data.attributes` fields:

```json
{
  "data": {
    "id": null,
    "type": "health-check",
    "attributes": {
      "timestamp": "2026-09-09T12:00:00.000000Z",
      "services": [
        {
          "name": "Database",
          "connection": "sqlite",
          "status": "up",
          "message": null,
          "responseTime": 4.12
        }
      ]
    }
  }
}
```

### Configuration

```php
return [
    'route' => [
        'path' => env('HEALTH_PATH', '/health'),
        'name' => 'health',
    ],

    'response' => [
        'service_timeout' => 2,
        'include_details' => env('HEALTH_DEBUG', false),
    ],

    'services' => [
        'database' => [
            'enabled' => true,
            'connection' => env('DB_CONNECTION', 'sqlite'),
            'class' => DatabaseService::class,
        ],
    ],
];
```

### Custom Health Services

Create a class that extends `HealthService`. The class must provide a display name, connection label, and asynchronous `checkAsync()` method.

```php
<?php

namespace App\Health;

use ErvinsVilumsons\LaravelHealth\Services\HealthService;
use Illuminate\Support\Facades\Config;
use React\Promise\PromiseInterface;
use React\Socket\Connector;

class BillingService extends HealthService
{
    private readonly string $host;

    private readonly int $port;

    public function __construct()
    {
        $this->host = Config::string('billing.host');
        $this->port = (int) Config::string('billing.port');
    }

    public function name(): string
    {
        return 'Billing';
    }

    public function connection(): mixed
    {
        return Config::string('health-manager.services.billing.connection');
    }

    protected function checkAsync(): PromiseInterface
    {
        $connector = new Connector(['timeout' => $this->getTimeout()]);

        return $connector
            ->connect("{$this->host}:{$this->port}")
            ->then(function ($connection): void {
                $connection->close();
            });
    }
}
```

Register it in `config/health-manager.php`:

```php
'billing' => [
    'enabled' => true,
    'connection' => 'billing.internal:443',
    'class' => \App\Health\BillingService::class,
],
```

### Failure Handling

A failed `checkAsync()` promise does not make the whole report fail. If one of services is marked `down`, a `ServiceFailed` event is dispatched. Response messages are only included when `health-manager.response.include_details` is enabled.

Then customize `app/Listeners/HandleFailedService.php` to send alerts, log metadata, or notify an incident system:

```php
<?php

namespace App\Listeners;

use ErvinsVilumsons\LaravelHealth\Events\ServiceFailed;
use Illuminate\Support\Facades\Log;

class HandleFailedService
{
    public function handle(ServiceFailed $event): void
    {
        Log::error('Health check failed', [
            'title' => $event->title,
            'message' => $event->message,
            'context' => $event->context,
            'level' => $event->level,
        ]);
    }
}
```

## ⚖️ License

Laravel Health Manager is released under the [MIT License](LICENSE).
