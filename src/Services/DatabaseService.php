<?php

declare(strict_types=1);

namespace ErvinsVilumsons\LaravelHealth\Services;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use React\Promise\PromiseInterface;
use React\Socket\Connector;

use function React\Promise\reject;
use function React\Promise\resolve;

class DatabaseService extends HealthService
{
    private readonly string $database;

    public function __construct()
    {
        $this->database = Config::string('database.default');
    }

    public function name(): string
    {
        return 'Database';
    }

    public function connection(): string
    {
        return Config::string('health-manager.services.database.connection');
    }

    /**
     * @return PromiseInterface<void>
     */
    public function checkAsync(): PromiseInterface
    {
        switch ($this->database) {
            case 'sqlite':
                try {
                    DB::connection()->select('SELECT 1');

                    return resolve(null)->then(static function (): void {});
                } catch (\Throwable $e) {
                    return reject($e);
                }
            default:
                $host = Config::string("database.connections.{$this->database}.host");
                $port = (int) Config::string("database.connections.{$this->database}.port");
                $connector = new Connector(['timeout' => self::getTimeout()]);

                return $connector
                    ->connect("{$host}:{$port}")
                    ->then(
                        function ($connection): void {
                            $connection->close();
                        }
                    );
        }
    }
}
