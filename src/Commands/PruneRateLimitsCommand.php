<?php

declare(strict_types=1);

namespace ErvinsVilumsons\LaravelHealth\Commands;

use ErvinsVilumsons\LaravelHealth\Support\Filesystem;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use JsonException;

final class PruneRateLimitsCommand extends Command
{
    protected $signature = 'health:prune-rate-limits';

    protected $description = 'Remove expired health rate limiter state files.';

    public function handle(Filesystem $filesystem): int
    {
        $decaySeconds = Config::integer('health-manager.throttle.decay_seconds');
        $cutoff = time() - $decaySeconds;

        if (! is_dir($filesystem->directory)) {
            $this->info('Rate limiter directory does not exist. Nothing to prune.');

            return self::SUCCESS;
        }

        $files = glob($filesystem->directory.'/*.json');

        if ($files === false || $files === []) {
            $this->info('No rate limiter state files found.');

            return self::SUCCESS;
        }

        $pruned = 0;
        $kept = 0;

        foreach ($files as $file) {
            if ($this->isExpired($file, $cutoff)) {
                $filesystem->delete($file);
                $pruned++;
            } else {
                $kept++;
            }
        }

        $this->info("Pruned {$pruned} expired rate limiter file(s), kept {$kept}.");

        return self::SUCCESS;
    }

    private function isExpired(string $path, int $cutoff): bool
    {
        $contents = @file_get_contents($path);

        // Unreadable, empty, or malformed state is garbage — remove it.
        if ($contents === false || $contents === '') {
            return true;
        }

        try {
            $state = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return true;
        }

        if (
            ! is_array($state) ||
            ! isset($state['started_at']) ||
            ! is_int($state['started_at'])
        ) {
            return true;
        }

        return $state['started_at'] < $cutoff;
    }
}
