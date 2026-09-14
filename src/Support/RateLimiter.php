<?php

declare(strict_types=1);

namespace ErvinsVilumsons\LaravelHealth\Support;

use ErvinsVilumsons\LaravelHealth\Contracts\RateLimiterContract;
use Illuminate\Support\Facades\Config;

final readonly class RateLimiter implements RateLimiterContract
{
    private int $maxAttempts;

    private int $decaySeconds;

    public function __construct(private Filesystem $filesystem)
    {
        $this->maxAttempts = Config::integer('health-manager.throttle.max_attempts');
        $this->decaySeconds = Config::integer('health-manager.throttle.decay_seconds');
    }

    public function attempt(string $key): bool
    {
        $path = $this->path($key);

        if ($this->maxAttempts === 0) {
            return true;
        }

        if (! $this->filesystem->ensureDirectoryExists()) {
            return true;
        }

        $handle = $this->filesystem->open($path, 'c+');

        if ($handle === false) {
            return true;
        }

        try {
            if (! $this->filesystem->lockExclusive($handle)) {
                return true;
            }

            $state = $this->read($handle);
            $now = time();

            if (
                $state === null ||
                ($now - $state['started_at']) >= $this->decaySeconds
            ) {
                $state = [
                    'started_at' => $now,
                    'attempts' => 0,
                ];
            }

            if ($state['attempts'] >= $this->maxAttempts) {
                return false;
            }

            $state['attempts']++;

            // Fail-open: even if persistence fails, this attempt is allowed.
            $this->write($handle, $state);

            return true;
        } finally {
            $this->filesystem->unlock($handle);
            $this->filesystem->close($handle);
        }
    }

    public function remaining(string $key): int
    {
        $path = $this->path($key);

        if (! is_file($path)) {
            return $this->maxAttempts;
        }

        $handle = $this->filesystem->open($path, 'r');

        if ($handle === false) {
            return $this->maxAttempts;
        }

        try {
            if (! $this->filesystem->lockShared($handle)) {
                return $this->maxAttempts;
            }

            $state = $this->read($handle);

            if ($state === null) {
                return $this->maxAttempts;
            }

            if ((time() - $state['started_at']) >= $this->decaySeconds) {
                return $this->maxAttempts;
            }

            return max(
                0,
                $this->maxAttempts - $state['attempts'],
            );
        } finally {
            $this->filesystem->unlock($handle);
            $this->filesystem->close($handle);
        }
    }

    public function retryAfter(string $key): int
    {
        $path = $this->path($key);

        if (! is_file($path)) {
            return 0;
        }

        $handle = $this->filesystem->open($path, 'r');

        if ($handle === false) {
            return 0;
        }

        try {
            if (! $this->filesystem->lockShared($handle)) {
                return 0;
            }

            $state = $this->read($handle);

            if ($state === null) {
                return 0;
            }

            $remaining = $this->decaySeconds - (time() - $state['started_at']);

            return max(0, $remaining);
        } finally {
            $this->filesystem->unlock($handle);
            $this->filesystem->close($handle);
        }
    }

    public function clear(string $key): void
    {
        $this->filesystem->delete($this->path($key));
    }

    public function path(string $key): string
    {
        return $this->filesystem->directory.'/'.hash('sha256', $key).'.json';
    }

    /**
     * @param  resource  $handle
     * @return array{started_at: int, attempts: int}|null
     */
    private function read($handle): ?array
    {
        rewind($handle);

        $contents = stream_get_contents($handle);

        if ($contents === false || $contents === '') {
            return null;
        }

        try {
            $state = json_decode(
                $contents,
                true,
                512,
                JSON_THROW_ON_ERROR,
            );
        } catch (\JsonException) {
            return null;
        }

        if (! is_array($state) ||
            ! isset($state['started_at']) ||
            ! isset($state['attempts']) ||
            ! is_int($state['started_at']) ||
            ! is_int($state['attempts'])
        ) {
            return null;
        }

        return [
            'started_at' => $state['started_at'],
            'attempts' => $state['attempts'],
        ];
    }

    /**
     * @param  resource  $handle
     * @param  array{started_at: int, attempts: int}  $state
     */
    private function write($handle, array $state): bool
    {
        rewind($handle);

        if (! $this->filesystem->truncate($handle, 0)) {
            return false;
        }

        $contents = json_encode(
            $state,
            JSON_THROW_ON_ERROR,
        );

        if ($this->filesystem->write($handle, $contents) === false) {
            return false;
        }

        return $this->filesystem->flush($handle);
    }
}
