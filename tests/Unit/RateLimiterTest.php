<?php

declare(strict_types=1);

namespace ErvinsVilumsons\LaravelHealth\Tests\Unit;

use ErvinsVilumsons\LaravelHealth\Support\Filesystem;
use ErvinsVilumsons\LaravelHealth\Support\RateLimiter;
use ErvinsVilumsons\LaravelHealth\Tests\TestCase;
use Illuminate\Support\Facades\Config;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;

#[AllowMockObjectsWithoutExpectations]
class RateLimiterTest extends TestCase
{
    /** @var Filesystem&MockObject */
    private $filesystem;

    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('health-manager.throttle.max_attempts', 3);
        Config::set('health-manager.throttle.decay_seconds', 60);

        $realFilesystem = new Filesystem;
        $this->directory = $realFilesystem->directory;
        $realFilesystem->ensureDirectoryExists();

        $this->filesystem = $this->getMockBuilder(Filesystem::class)
            ->enableOriginalConstructor()
            ->getMock();
    }

    protected function tearDown(): void
    {
        if (is_dir($this->directory)) {
            $this->removeDirectory($this->directory);
        }

        parent::tearDown();
    }

    public function test_path_returns_hashed_path(): void
    {
        $limiter = new RateLimiter($this->filesystem);

        self::assertSame(
            $this->directory.'/'.hash('sha256', 'test-key').'.json',
            $limiter->path('test-key'),
        );
    }

    public function test_attempt_creates_state_and_returns_true(): void
    {
        // Real filesystem: we actually need persistence to verify state.
        $limiter = new RateLimiter(new Filesystem);

        self::assertTrue($limiter->attempt('test-key'));

        self::assertSame(1, $this->readState($limiter->path('test-key'))['attempts']);
    }

    public function test_attempt_returns_true_when_directory_cannot_be_created(): void
    {
        $this->filesystem
            ->expects(self::once())
            ->method('ensureDirectoryExists')
            ->willReturn(false);

        $limiter = new RateLimiter($this->filesystem);

        self::assertTrue($limiter->attempt('test-key'));
    }

    public function test_attempt_returns_true_when_file_cannot_be_opened(): void
    {
        $this->filesystem->method('ensureDirectoryExists')->willReturn(true);

        $this->filesystem
            ->expects(self::once())
            ->method('open')
            ->willReturn(false);

        $limiter = new RateLimiter($this->filesystem);

        self::assertTrue($limiter->attempt('test-key'));
    }

    public function test_attempt_returns_true_when_exclusive_lock_cannot_be_acquired(): void
    {
        $handle = $this->openMemoryHandle();

        $this->filesystem->method('ensureDirectoryExists')->willReturn(true);
        $this->filesystem->method('open')->willReturn($handle);
        $this->filesystem->expects(self::once())->method('lockExclusive')->willReturn(false);
        $this->filesystem->expects(self::once())->method('unlock');
        $this->filesystem->expects(self::once())->method('close');

        $limiter = new RateLimiter($this->filesystem);

        self::assertTrue($limiter->attempt('test-key'));

        fclose($handle);
    }

    public function test_attempt_returns_true_when_write_fails(): void
    {
        $path = $this->directory.'/test.json';

        $this->writeStateFile($path, [
            'started_at' => time(),
            'attempts' => 0,
        ]);

        $handle = $this->openReadHandle($path);

        $this->filesystem->method('ensureDirectoryExists')->willReturn(true);
        $this->filesystem->method('open')->willReturn($handle);
        $this->filesystem->method('lockExclusive')->willReturn(true);
        $this->filesystem->expects(self::once())->method('unlock');
        $this->filesystem->expects(self::once())->method('close');

        $limiter = new RateLimiter($this->filesystem);

        self::assertTrue($limiter->attempt('test-key'));

        fclose($handle);
    }

    public function test_attempt_increments_existing_state(): void
    {
        $limiter = new RateLimiter(new Filesystem);

        $limiter->attempt('test-key');
        $limiter->attempt('test-key');

        self::assertSame(2, $this->readState($limiter->path('test-key'))['attempts']);
    }

    public function test_attempt_returns_false_when_max_attempts_reached(): void
    {
        $limiter = new RateLimiter(new Filesystem);

        self::assertTrue($limiter->attempt('test-key'));
        self::assertTrue($limiter->attempt('test-key'));
        self::assertTrue($limiter->attempt('test-key'));
        self::assertFalse($limiter->attempt('test-key'));
    }

    public function test_attempt_resets_expired_state(): void
    {
        $limiter = new RateLimiter(new Filesystem);

        $this->writeStateFile($limiter->path('test-key'), [
            'started_at' => time() - 120,
            'attempts' => 3,
        ]);

        self::assertTrue($limiter->attempt('test-key'));

        self::assertSame(1, $this->readState($limiter->path('test-key'))['attempts']);
    }

    public function test_attempt_recovers_from_invalid_json(): void
    {
        $limiter = new RateLimiter(new Filesystem);

        file_put_contents($limiter->path('test-key'), '{invalid json');

        self::assertTrue($limiter->attempt('test-key'));

        self::assertSame(1, $this->readState($limiter->path('test-key'))['attempts']);
    }

    public function test_remaining_returns_max_attempts_when_file_does_not_exist(): void
    {
        $limiter = new RateLimiter($this->filesystem);

        self::assertSame(3, $limiter->remaining('missing-key'));
    }

    public function test_remaining_returns_max_attempts_when_file_cannot_be_opened(): void
    {
        $limiter = new RateLimiter($this->filesystem);

        file_put_contents($limiter->path('test-key'), '{}');

        $this->filesystem
            ->expects(self::once())
            ->method('open')
            ->willReturn(false);

        self::assertSame(3, $limiter->remaining('test-key'));
    }

    public function test_remaining_returns_max_attempts_when_shared_lock_fails(): void
    {
        $limiter = new RateLimiter($this->filesystem);

        $handle = $this->writeStateAndOpen($limiter, 'test-key', [
            'started_at' => time(),
            'attempts' => 1,
        ]);

        $this->filesystem->expects(self::once())->method('open')->willReturn($handle);
        $this->filesystem->expects(self::once())->method('lockShared')->willReturn(false);
        $this->filesystem->expects(self::once())->method('unlock');
        $this->filesystem->expects(self::once())->method('close');

        self::assertSame(3, $limiter->remaining('test-key'));

        fclose($handle);
    }

    public function test_remaining_returns_remaining_attempts(): void
    {
        $limiter = new RateLimiter($this->filesystem);

        $this->mockReadableState($limiter, 'test-key', [
            'started_at' => time(),
            'attempts' => 1,
        ]);

        self::assertSame(2, $limiter->remaining('test-key'));
    }

    public function test_retry_after_returns_zero_when_file_does_not_exist(): void
    {
        $limiter = new RateLimiter($this->filesystem);

        self::assertSame(0, $limiter->retryAfter('missing-key'));
    }

    public function test_retry_after_returns_zero_when_file_cannot_be_opened(): void
    {
        $limiter = new RateLimiter($this->filesystem);

        file_put_contents($limiter->path('test-key'), '{}');

        $this->filesystem
            ->expects(self::once())
            ->method('open')
            ->willReturn(false);

        self::assertSame(0, $limiter->retryAfter('test-key'));
    }

    public function test_retry_after_returns_zero_when_shared_lock_fails(): void
    {
        $limiter = new RateLimiter($this->filesystem);

        $handle = $this->writeStateAndOpen($limiter, 'test-key', [
            'started_at' => time(),
            'attempts' => 1,
        ]);

        $this->filesystem->method('open')->willReturn($handle);
        $this->filesystem->expects(self::once())->method('lockShared')->willReturn(false);
        $this->filesystem->expects(self::once())->method('unlock');
        $this->filesystem->expects(self::once())->method('close');

        self::assertSame(0, $limiter->retryAfter('test-key'));

        fclose($handle);
    }

    public function test_retry_after_returns_remaining_seconds(): void
    {
        $limiter = new RateLimiter($this->filesystem);

        $this->mockReadableState($limiter, 'test-key', [
            'started_at' => time() - 10,
            'attempts' => 1,
        ]);

        $retryAfter = $limiter->retryAfter('test-key');

        self::assertGreaterThanOrEqual(49, $retryAfter);
        self::assertLessThanOrEqual(50, $retryAfter);
    }

    public function test_clear_deletes_state(): void
    {
        $limiter = new RateLimiter($this->filesystem);

        file_put_contents($limiter->path('test-key'), '{}');

        $this->filesystem
            ->expects(self::once())
            ->method('delete')
            ->with($limiter->path('test-key'));

        $limiter->clear('test-key');
    }

    public function test_remaining_returns_max_attempts_when_state_is_invalid(): void
    {
        $limiter = new RateLimiter($this->filesystem);

        $this->mockReadableState($limiter, 'test-key', [
            'started_at' => 'invalid',
            'attempts' => 1,
        ]);

        self::assertSame(3, $limiter->remaining('test-key'));
    }

    public function test_retry_after_returns_zero_when_state_is_invalid(): void
    {
        $limiter = new RateLimiter($this->filesystem);

        $this->mockReadableState($limiter, 'test-key', [
            'started_at' => 'invalid',
            'attempts' => 1,
        ]);

        self::assertSame(0, $limiter->retryAfter('test-key'));
    }

    public function test_remaining_returns_max_attempts_when_state_is_empty(): void
    {
        $limiter = new RateLimiter($this->filesystem);

        $this->mockReadableContent($limiter, 'test-key', '');

        self::assertSame(3, $limiter->remaining('test-key'));
    }

    public function test_retry_after_returns_zero_when_state_is_empty(): void
    {
        $limiter = new RateLimiter($this->filesystem);

        $this->mockReadableContent($limiter, 'test-key', '');

        self::assertSame(0, $limiter->retryAfter('test-key'));
    }

    public function test_attempt_returns_false_when_existing_state_reaches_limit(): void
    {
        $limiter = new RateLimiter(new Filesystem);
        $startedAt = time();

        $this->writeStateFile($limiter->path('test-key'), [
            'started_at' => $startedAt,
            'attempts' => 3,
        ]);

        self::assertFalse($limiter->attempt('test-key'));

        $state = $this->readState($limiter->path('test-key'));
        self::assertSame(3, $state['attempts']);
        self::assertSame($startedAt, $state['started_at']);
    }

    public function test_attempt_returns_true_when_flush_fails(): void
    {
        $this->mockWorkingFilesystem();

        $this->filesystem->method('truncate')->willReturn(true);
        $this->filesystem->method('write')->willReturn(10);
        $this->filesystem->expects(self::once())->method('flush')->willReturn(false);

        $limiter = new RateLimiter($this->filesystem);

        self::assertTrue($limiter->attempt('test-key'));
    }

    public function test_attempt_returns_true_when_truncate_fails(): void
    {
        $handle = $this->openMemoryHandle();

        $this->filesystem->method('ensureDirectoryExists')->willReturn(true);
        $this->filesystem->method('open')->willReturn($handle);
        $this->filesystem->method('lockExclusive')->willReturn(true);
        $this->filesystem->method('unlock')->willReturn(true);
        $this->filesystem->method('close')->willReturnCallback(
            static function ($handle): void {
                if (is_resource($handle)) {
                    fclose($handle);
                }
            },
        );

        $this->filesystem
            ->expects(self::once())
            ->method('truncate')
            ->willReturn(false);

        $limiter = new RateLimiter($this->filesystem);

        self::assertTrue($limiter->attempt('test-key'));
    }

    public function test_attempt_returns_true_when_handle_write_fails(): void
    {
        $handle = $this->openMemoryHandle();

        $this->filesystem->method('ensureDirectoryExists')->willReturn(true);
        $this->filesystem->method('open')->willReturn($handle);
        $this->filesystem->method('lockExclusive')->willReturn(true);
        $this->filesystem->method('unlock')->willReturn(true);
        $this->filesystem->method('close')->willReturnCallback(
            static function ($handle): void {
                if (is_resource($handle)) {
                    fclose($handle);
                }
            },
        );

        $this->filesystem->method('truncate')->willReturn(true);

        $this->filesystem
            ->expects(self::once())
            ->method('write')
            ->willReturn(false);

        $limiter = new RateLimiter($this->filesystem);

        self::assertTrue($limiter->attempt('test-key'));
    }

    public function test_remaining_returns_max_attempts_when_state_is_expired(): void
    {
        $limiter = new RateLimiter(new Filesystem);

        $this->writeStateFile($limiter->path('test-key'), [
            'started_at' => time() - 120,
            'attempts' => 1,
        ]);

        self::assertSame(3, $limiter->remaining('test-key'));
    }

    public function test_remaining_clamps_to_zero_when_attempts_exceed_max(): void
    {
        $limiter = new RateLimiter(new Filesystem);

        // Bypass attempt() to simulate a defensive / hand-edited state file.
        $this->writeStateFile($limiter->path('test-key'), [
            'started_at' => time(),
            'attempts' => 5,
        ]);

        self::assertSame(0, $limiter->remaining('test-key'));
    }

    public function test_retry_after_returns_zero_when_state_is_expired(): void
    {
        $limiter = new RateLimiter(new Filesystem);

        $this->writeStateFile($limiter->path('test-key'), [
            'started_at' => time() - 120,
            'attempts' => 1,
        ]);

        self::assertSame(0, $limiter->retryAfter('test-key'));
    }

    /**
     * Configure the mock so locking/opening/closing operations succeed.
     */
    private function mockWorkingFilesystem(): void
    {
        $this->filesystem->method('ensureDirectoryExists')->willReturn(true);

        $this->filesystem->method('open')->willReturnCallback(
            static fn (string $path, string $mode) => fopen($path, $mode),
        );

        $this->filesystem->method('lockExclusive')->willReturn(true);
        $this->filesystem->method('lockShared')->willReturn(true);
        $this->filesystem->method('unlock')->willReturn(true);

        $this->filesystem->method('close')->willReturnCallback(
            static function ($handle): void {
                if (is_resource($handle)) {
                    fclose($handle);
                }
            },
        );
    }

    /**
     * Write the given state as JSON and configure the mock so reading it
     * succeeds via a shared lock.
     *
     * @param  array{started_at: int|string, attempts: int}  $state
     */
    private function mockReadableState(RateLimiter $limiter, string $key, array $state): void
    {
        $this->mockReadableContent(
            $limiter,
            $key,
            json_encode($state, JSON_THROW_ON_ERROR),
        );
    }

    /**
     * Write raw content to the rate-limiter file and configure the mock so
     * reading it succeeds via a shared lock.
     */
    private function mockReadableContent(RateLimiter $limiter, string $key, string $content): void
    {
        $handle = $this->writeContentAndOpen($limiter, $key, $content);

        $this->filesystem->method('open')->willReturn($handle);
        $this->filesystem->method('lockShared')->willReturn(true);
        $this->filesystem->method('unlock')->willReturn(true);
        $this->filesystem->method('close')->willReturnCallback(
            static function ($handle): void {
                if (is_resource($handle)) {
                    fclose($handle);
                }
            },
        );
    }

    public function test_attempt_returns_true_without_touching_filesystem_when_throttle_disabled(): void
    {
        Config::set('health-manager.throttle.max_attempts', 0);

        // Guard short-circuits before any I/O.
        $this->filesystem->expects(self::never())->method('ensureDirectoryExists');
        $this->filesystem->expects(self::never())->method('open');
        $this->filesystem->expects(self::never())->method('lockExclusive');

        $limiter = new RateLimiter($this->filesystem);

        self::assertTrue($limiter->attempt('test-key'));
        self::assertTrue($limiter->attempt('test-key'));
        self::assertTrue($limiter->attempt('test-key'));
        self::assertTrue($limiter->attempt('test-key'));
    }

    /**
     * @param  array{started_at: int|string, attempts: int}  $state
     * @return resource
     */
    private function writeStateAndOpen(RateLimiter $limiter, string $key, array $state)
    {
        return $this->writeContentAndOpen(
            $limiter,
            $key,
            json_encode($state, JSON_THROW_ON_ERROR),
        );
    }

    /**
     * @return resource
     */
    private function writeContentAndOpen(RateLimiter $limiter, string $key, string $content)
    {
        $path = $limiter->path($key);

        file_put_contents($path, $content);

        return $this->openReadHandle($path);
    }

    /**
     * @param  array{started_at: int|string, attempts: int}  $state
     */
    private function writeStateFile(string $path, array $state): void
    {
        file_put_contents(
            $path,
            json_encode($state, JSON_THROW_ON_ERROR),
        );
    }

    /**
     * @return resource
     */
    private function openReadHandle(string $path)
    {
        $handle = fopen($path, 'r');

        self::assertIsResource($handle);

        return $handle;
    }

    /**
     * @return resource
     */
    private function openMemoryHandle()
    {
        $handle = fopen('php://memory', 'c+');

        self::assertIsResource($handle);

        return $handle;
    }

    /**
     * @return array{started_at: int, attempts: int}
     */
    private function readState(string $path): array
    {
        $contents = file_get_contents($path);

        self::assertIsString($contents);

        $state = json_decode(
            $contents,
            true,
            512,
            JSON_THROW_ON_ERROR,
        );

        self::assertIsArray($state);
        self::assertArrayHasKey('started_at', $state);
        self::assertArrayHasKey('attempts', $state);
        self::assertIsInt($state['started_at']);
        self::assertIsInt($state['attempts']);

        return [
            'started_at' => $state['started_at'],
            'attempts' => $state['attempts'],
        ];
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
