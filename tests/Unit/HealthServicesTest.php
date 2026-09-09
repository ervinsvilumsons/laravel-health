<?php

namespace ErvinsVilumsons\LaravelHealth\Tests\Unit;

use ErvinsVilumsons\LaravelHealth\Services\CacheService;
use ErvinsVilumsons\LaravelHealth\Services\DatabaseService;
use ErvinsVilumsons\LaravelHealth\Services\HealthService;
use ErvinsVilumsons\LaravelHealth\Services\MailService;
use ErvinsVilumsons\LaravelHealth\Services\QueueService;
use ErvinsVilumsons\LaravelHealth\Services\RedisService;
use ErvinsVilumsons\LaravelHealth\Tests\Support\FailingHealthService;
use ErvinsVilumsons\LaravelHealth\Tests\TestCase;
use Illuminate\Support\Facades\DB;
use React\Promise\PromiseInterface;

use function React\Async\await;

class HealthServicesTest extends TestCase
{
    public function test_services_expose_their_configured_connections(): void
    {
        config()->set('health-manager.services.cache.connection', 'array');
        config()->set('health-manager.services.database.connection', 'mysql');
        config()->set('health-manager.services.mail.connection', 'smtp');
        config()->set('health-manager.services.queue.connection', 'sync');
        config()->set('health-manager.services.redis.connection', 'default');
        config()->set('database.connections.testing.host', '127.0.0.1');
        config()->set('database.connections.testing.port', '3306');
        config()->set('mail.mailers.smtp.host', '127.0.0.1');
        config()->set('mail.mailers.smtp.port', '25');
        config()->set('database.redis.default.host', '127.0.0.1');
        config()->set('database.redis.default.port', '6379');

        self::assertService(new CacheService, 'Cache', 'array');
        self::assertService(new DatabaseService, 'Database', 'mysql');
        self::assertService(new MailService, 'Mail', 'smtp');
        self::assertService(new QueueService, 'Queue', 'sync');
        self::assertService(new RedisService, 'Redis', 'default');

        $databaseCheck = (new DatabaseService)->checkAsync();
        $mailCheck = (new MailService)->checkAsync();
        $redisCheck = (new RedisService)->checkAsync();

        self::assertInstanceOf(PromiseInterface::class, $databaseCheck);
        self::assertInstanceOf(PromiseInterface::class, $mailCheck);
        self::assertInstanceOf(PromiseInterface::class, $redisCheck);

        $ignoreConnectionFailure = static fn (\Throwable $exception): null => null;
        $databaseCheck->catch($ignoreConnectionFailure);
        $mailCheck->catch($ignoreConnectionFailure);
        $redisCheck->catch($ignoreConnectionFailure);
    }

    public function test_cache_and_queue_checks_succeed_without_external_connections(): void
    {
        config()->set('health-manager.services.cache.connection', 'array');
        config()->set('health-manager.services.queue.connection', 'sync');

        $cache = new CacheService;
        $queue = new QueueService;

        self::assertInstanceOf(PromiseInterface::class, $cache->checkAsync());
        self::assertInstanceOf(PromiseInterface::class, $queue->checkAsync());
    }

    public function test_failed_health_checks_resolve_as_down(): void
    {
        config()->set('health-manager.response.include_details', true);

        $service = new FailingHealthService;

        $service->statusAsync();

        self::assertSame(HealthService::STATUS_DOWN, $service->status());
        self::assertSame('Zulu service failed: test failure', $service->message());
        self::assertNotNull($service->responseTime());
    }

    public function test_sqlite_database_checks_succeed_and_fail_as_promises(): void
    {
        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite.database', ':memory:');

        await((new DatabaseService)->checkAsync());

        DB::shouldReceive('connection')->andThrow(new \RuntimeException('database failure'));

        $service = new DatabaseService;
        $service->statusAsync();

        self::assertSame(HealthService::STATUS_DOWN, $service->status());
    }

    public function test_connector_services_close_successful_connections(): void
    {
        $server = stream_socket_server('tcp://127.0.0.1:0', $errorCode, $errorMessage);
        self::assertNotFalse($server, $errorMessage ?? 'Unable to create test socket.');

        /** @var resource $server */
        $address = stream_socket_get_name($server, false);
        self::assertIsString($address);
        $port = (int) substr($address, strrpos($address, ':') + 1);

        config()->set('database.default', 'mysql');
        config()->set('database.connections.mysql.host', '127.0.0.1');
        config()->set('database.connections.mysql.port', (string) $port);
        config()->set('mail.mailers.smtp.host', '127.0.0.1');
        config()->set('mail.mailers.smtp.port', (string) $port);
        config()->set('database.redis.default.host', '127.0.0.1');
        config()->set('database.redis.default.port', (string) $port);

        await((new DatabaseService)->checkAsync());
        await((new MailService)->checkAsync());
        await((new RedisService)->checkAsync());

        fclose($server);
    }

    private static function assertService(HealthService $service, string $name, string $connection): void
    {
        self::assertSame($name, $service->name());
        self::assertSame($connection, $service->connection());
        self::assertNull($service->message());
    }
}
