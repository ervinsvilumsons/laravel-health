<?php

declare(strict_types=1);

namespace ErvinsVilumsons\LaravelHealth\Services;

use ErvinsVilumsons\LaravelHealth\Contracts\HealthServiceContract;
use Illuminate\Support\Facades\Config;
use React\Promise\PromiseInterface;

abstract class HealthService implements HealthServiceContract
{
    protected ?string $message = null;

    protected ?float $responseTime = null;

    protected string $status = self::STATUS_DOWN;

    public static function getTimeout(): int
    {
        return Config::integer('health-manager.response.service_timeout');
    }

    private static function includeDetails(): bool
    {
        return Config::boolean('health-manager.response.include_details');
    }

    abstract public function name(): string;

    abstract public function connection(): mixed;

    public function status(): string
    {
        return $this->status;
    }

    public function message(): ?string
    {
        return self::includeDetails() ? $this->message : null;
    }

    public function responseTime(): ?float
    {
        return $this->responseTime;
    }

    public function skip(string $message): void
    {
        $this->status = self::STATUS_SKIPPED;
        $this->message = $message;
    }

    /**
     * @return PromiseInterface<null|void>
     */
    abstract protected function checkAsync(): PromiseInterface;

    public function statusAsync(): PromiseInterface
    {
        $start = microtime(true);

        return $this->checkAsync()
            ->then(
                function (): void {
                    $this->status = self::STATUS_UP;
                },
                function (\Throwable $e): void {
                    $this->status = self::STATUS_DOWN;
                    $this->message = "{$this->name()} service failed: {$e->getMessage()}";
                }
            )->then(
                function () use ($start): void {
                    $this->responseTime = round((microtime(true) - $start) * 1000, 2);
                }
            );
    }
}
