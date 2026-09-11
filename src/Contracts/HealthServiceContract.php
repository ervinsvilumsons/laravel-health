<?php

declare(strict_types=1);

namespace ErvinsVilumsons\LaravelHealth\Contracts;

use React\Promise\PromiseInterface;

interface HealthServiceContract
{
    const string STATUS_UP = 'up';

    const string STATUS_SKIPPED = 'skipped';

    const string STATUS_DOWN = 'down';

    /**
     * @return PromiseInterface<void>
     */
    public function statusAsync(): PromiseInterface;

    public function skip(string $reason): void;

    public function name(): string;

    public function connection(): mixed;

    public function status(): ?string;

    public function message(): ?string;

    public function responseTime(): ?float;

    public static function getTimeout(): int;
}
