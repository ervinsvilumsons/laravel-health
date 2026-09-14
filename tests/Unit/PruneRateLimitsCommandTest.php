<?php

declare(strict_types=1);

namespace ErvinsVilumsons\LaravelHealth\Tests\Unit;

use ErvinsVilumsons\LaravelHealth\Support\Filesystem;
use ErvinsVilumsons\LaravelHealth\Tests\TestCase;
use Illuminate\Support\Facades\Config;
use Illuminate\Testing\PendingCommand;

class PruneRateLimitsCommandTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('health-manager.throttle.max_attempts', 3);
        Config::set('health-manager.throttle.decay_seconds', 60);

        $filesystem = new Filesystem;
        $this->directory = $filesystem->directory;

        if (is_dir($this->directory)) {
            $this->removeDirectory($this->directory);
        }
    }

    protected function tearDown(): void
    {
        if (is_dir($this->directory)) {
            $this->removeDirectory($this->directory);
        }

        parent::tearDown();
    }

    public function test_prunes_expired_files_and_keeps_fresh_and_invalid_removed(): void
    {
        $filesystem = new Filesystem;
        $filesystem->ensureDirectoryExists();

        $expired = $this->directory.'/expired.json';
        $fresh = $this->directory.'/fresh.json';
        $invalid = $this->directory.'/invalid.json';

        file_put_contents($expired, json_encode([
            'started_at' => time() - 120,
            'attempts' => 3,
        ], JSON_THROW_ON_ERROR));

        file_put_contents($fresh, json_encode([
            'started_at' => time(),
            'attempts' => 1,
        ], JSON_THROW_ON_ERROR));

        file_put_contents($invalid, '{not json');

        $this->pendingCommand('health:prune-rate-limits')
            ->expectsOutput('Pruned 2 expired rate limiter file(s), kept 1.')
            ->assertSuccessful();

        self::assertFileDoesNotExist($expired);
        self::assertFileExists($fresh);
        self::assertFileDoesNotExist($invalid);
    }

    public function test_reports_when_directory_is_missing(): void
    {
        self::assertDirectoryDoesNotExist($this->directory);

        $this->pendingCommand('health:prune-rate-limits')
            ->expectsOutput('Rate limiter directory does not exist. Nothing to prune.')
            ->assertSuccessful();
    }

    public function test_reports_when_directory_is_empty(): void
    {
        (new Filesystem)->ensureDirectoryExists();

        $this->pendingCommand('health:prune-rate-limits')
            ->expectsOutput('No rate limiter state files found.')
            ->assertSuccessful();
    }

    public function test_prunes_empty_file_and_state_with_non_int_started_at(): void
    {
        $filesystem = new Filesystem;
        $filesystem->ensureDirectoryExists();

        $empty = $this->directory.'/empty.json';
        $badType = $this->directory.'/bad-type.json';

        file_put_contents($empty, '');
        file_put_contents($badType, json_encode([
            'started_at' => 'yesterday',
            'attempts' => 1,
        ], JSON_THROW_ON_ERROR));

        $this->pendingCommand('health:prune-rate-limits')
            ->expectsOutput('Pruned 2 expired rate limiter file(s), kept 0.')
            ->assertSuccessful();

        self::assertFileDoesNotExist($empty);
        self::assertFileDoesNotExist($badType);
    }

    private function pendingCommand(string $command): PendingCommand
    {
        $pending = $this->artisan($command);

        self::assertInstanceOf(PendingCommand::class, $pending);

        return $pending;
    }

    private function removeDirectory(string $directory): void
    {
        $files = scandir($directory);

        if ($files === false) {
            return;
        }

        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }

            $path = $directory.'/'.$file;

            if (is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                unlink($path);
            }
        }

        rmdir($directory);
    }
}
