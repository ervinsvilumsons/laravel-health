<?php

declare(strict_types=1);

namespace ErvinsVilumsons\LaravelHealth\Contracts;

interface RateLimiterContract
{
    public function attempt(string $key): bool;

    public function remaining(string $key): int;

    public function retryAfter(string $key): int;

    public function clear(string $key): void;
}
