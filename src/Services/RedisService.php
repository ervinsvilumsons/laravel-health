<?php

declare(strict_types=1);

namespace ErvinsVilumsons\LaravelHealth\Services;

use Illuminate\Support\Facades\Config;
use React\Promise\PromiseInterface;
use React\Socket\Connector;

class RedisService extends HealthService
{
    private readonly string $host;

    private readonly int $port;

    public function __construct()
    {
        $this->host = Config::string('database.redis.default.host');
        $this->port = (int) Config::string('database.redis.default.port');
    }

    public function name(): string
    {
        return 'Redis';
    }

    public function connection(): mixed
    {
        return Config::string('health-manager.services.redis.connection');
    }

    /**
     * @return PromiseInterface<void>
     */
    public function checkAsync(): PromiseInterface
    {
        $connector = new Connector(['timeout' => self::getTimeout()]);

        return $connector
            ->connect("{$this->host}:{$this->port}")
            ->then(
                function ($connection): void {
                    $connection->close();
                }
            );
    }
}
