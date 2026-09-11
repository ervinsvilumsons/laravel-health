<?php

declare(strict_types=1);

namespace ErvinsVilumsons\LaravelHealth\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use React\Promise\PromiseInterface;

use function React\Promise\resolve;

class CacheService extends HealthService
{
    public function name(): string
    {
        return 'Cache';
    }

    public function connection(): mixed
    {
        return Config::get('health-manager.services.cache.connection');
    }

    /**
     * @return PromiseInterface<null|void>
     */
    public function checkAsync(): PromiseInterface
    {
        return resolve(null)->then(static function (): void {
            Cache::get('health-manager:check');
        });
    }
}
