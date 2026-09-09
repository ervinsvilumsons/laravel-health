<?php

declare(strict_types=1);

namespace ErvinsVilumsons\LaravelHealth\Services;

use Illuminate\Support\Facades\Config;
use React\Promise\PromiseInterface;

use function React\Promise\resolve;

class QueueService extends HealthService
{
    public function name(): string
    {
        return 'Queue';
    }

    public function connection(): string
    {
        return Config::string('health-manager.services.queue.connection');
    }

    /**
     * @return PromiseInterface<null|void>
     */
    public function checkAsync(): PromiseInterface
    {
        return resolve(null)->then(static function (): void {});
    }
}
