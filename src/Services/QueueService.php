<?php

declare(strict_types=1);

namespace ErvinsVilumsons\LaravelHealth\Services;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Queue;
use React\Promise\PromiseInterface;

use function React\Promise\resolve;

class QueueService extends HealthService
{
    public function name(): string
    {
        return 'Queue';
    }

    public function connection(): mixed
    {
        /** @var string|null $connection */
        $connection = Config::get('health-manager.services.queue.connection');

        return is_string($connection) ? $connection : null;
    }

    /**
     * @return PromiseInterface<null|void>
     */
    public function checkAsync(): PromiseInterface
    {
        return resolve(null)->then(static function (): void {
            Queue::connection(Config::string('queue.default', 'sync'))->size();
        });
    }
}
