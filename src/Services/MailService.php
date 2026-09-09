<?php

declare(strict_types=1);

namespace ErvinsVilumsons\LaravelHealth\Services;

use Illuminate\Support\Facades\Config;
use React\Promise\PromiseInterface;
use React\Socket\Connector;

class MailService extends HealthService
{
    private readonly string $host;

    private readonly int $port;

    public function __construct()
    {
        $this->host = Config::string('mail.mailers.smtp.host');
        $this->port = (int) Config::string('mail.mailers.smtp.port');
    }

    public function name(): string
    {
        return 'Mail';
    }

    public function connection(): string
    {
        return Config::string('health-manager.services.mail.connection');
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
