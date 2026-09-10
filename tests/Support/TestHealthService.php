<?php

namespace ErvinsVilumsons\LaravelHealth\Tests\Support;

use ErvinsVilumsons\LaravelHealth\Services\HealthService;
use React\Promise\PromiseInterface;

use function React\Promise\reject;
use function React\Promise\resolve;

class TestHealthService extends HealthService
{
    public function __construct(
        private readonly string $serviceName,
        private readonly bool $shouldFail = false,
    ) {}

    protected function checkAsync(): PromiseInterface
    {
        if ($this->shouldFail) {
            return reject(new \RuntimeException('test failure'));
        }

        return resolve(null);
    }

    public function name(): string
    {
        return $this->serviceName;
    }

    public function connection(): mixed
    {
        return 'test';
    }
}
