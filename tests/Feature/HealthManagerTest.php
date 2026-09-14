<?php

namespace ErvinsVilumsons\LaravelHealth\Tests\Feature;

use ErvinsVilumsons\LaravelHealth\HealthManager;
use ErvinsVilumsons\LaravelHealth\Services\DatabaseService;
use ErvinsVilumsons\LaravelHealth\Support\RateLimiter;
use ErvinsVilumsons\LaravelHealth\Tests\Support\FailingHealthService;
use ErvinsVilumsons\LaravelHealth\Tests\Support\PassingHealthService;
use ErvinsVilumsons\LaravelHealth\Tests\TestCase;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Config;

class HealthManagerTest extends TestCase
{
    private string $path;

    protected function setUp(): void
    {
        parent::setUp();

        $prefix = 'api';
        $path = trim(Config::string('health-manager.route.path'), '/');

        $this->path = "/{$prefix}/{$path}";
    }

    public function test_report_contains_no_services_when_all_services_are_disabled(): void
    {
        config()->set('health-manager.services', [
            'database' => [
                'enabled' => false,
                'class' => DatabaseService::class,
            ],
        ]);

        $response = $this->getJson($this->path);

        $response->assertOk();
        self::assertSame([], $response->json('data.attributes.services'));
    }

    public function test_checks_enabled_services_and_reports_their_statuses(): void
    {
        config()->set('health-manager.response.include_details', true);

        config()->set('health-manager.services', [
            'failing' => [
                'enabled' => true,
                'class' => FailingHealthService::class,
            ],
            'passing' => [
                'enabled' => true,
                'class' => PassingHealthService::class,
            ],
        ]);

        $response = $this->getJson($this->path);

        $response->assertOk();
        $response->assertJsonFragment([
            'name' => 'Alpha',
            'status' => 'up',
        ]);
        $response->assertJsonFragment([
            'name' => 'Zulu',
            'status' => 'down',
            'message' => 'Zulu service failed: test failure',
        ]);

        /** @var array{0: array{name: string}, 1: array{name: string}} $services */
        $services = $response->json('data.attributes.services');

        self::assertCount(2, $services);
        self::assertSame('Alpha', $services[0]['name']);
        self::assertSame('Zulu', $services[1]['name']);
    }

    public function test_invalid_service_configuration_throws_a_useful_exception(): void
    {
        config()->set('health-manager.services', [
            'invalid' => [
                'enabled' => true,
                'class' => \stdClass::class,
            ],
        ]);

        try {
            app(HealthManager::class)->getReport();
            self::fail('Expected invalid health service configuration to throw.');
        } catch (\LogicException $exception) {
            self::assertStringContainsString(
                'Health service [stdClass] must extend',
                $exception->getMessage(),
            );
        }
    }

    public function test_dependent_services_run_after_a_healthy_parent(): void
    {
        config()->set('health-manager.services', [
            'parent' => [
                'enabled' => true,
                'class' => PassingHealthService::class,
            ],
            'child' => [
                'enabled' => true,
                'dependency' => 'Alpha',
                'class' => PassingHealthService::class,
            ],
        ]);

        $response = $this->getJson($this->path);

        $response->assertOk();
        /** @var array<int, array<string, mixed>> $services */
        $services = $response->json('data.attributes.services');
        self::assertCount(2, $services);
    }

    public function test_dependent_services_are_skipped_after_a_failed_parent(): void
    {
        config()->set('health-manager.response.include_details', true);
        config()->set('health-manager.services', [
            'parent' => [
                'enabled' => true,
                'class' => FailingHealthService::class,
            ],
            'child' => [
                'enabled' => true,
                'dependency' => 'Zulu',
                'class' => PassingHealthService::class,
            ],
        ]);

        $response = $this->getJson($this->path);

        $response->assertOk();
        $response->assertJsonFragment([
            'name' => 'Alpha',
            'status' => 'skipped',
            'message' => 'Zulu service failed: test failure',
        ]);
    }

    public function test_unresolved_dependencies_do_not_prevent_a_report(): void
    {
        config()->set('health-manager.services', [
            'parent' => [
                'enabled' => true,
                'class' => PassingHealthService::class,
            ],
            'child' => [
                'enabled' => true,
                'dependency' => 'Missing',
                'class' => PassingHealthService::class,
            ],
        ]);

        $response = $this->getJson($this->path);

        $response->assertOk();
        /** @var array<int, array<string, mixed>> $services */
        $services = $response->json('data.attributes.services');
        self::assertCount(1, $services);
    }

    public function test_service_sort_comparison_ignores_non_health_services(): void
    {
        $method = new \ReflectionMethod(HealthManager::class, 'compareServices');

        self::assertSame(0, $method->invoke(null, new \stdClass, new \stdClass));
    }

    public function test_rate_limit(): void
    {
        config()->set('health-manager.throttle.max_attempts', 2);
        config()->set('health-manager.services', []);

        $rateLimiter = app(RateLimiter::class);
        $rateLimiter->clear('127.0.0.1');

        $this->getJson($this->path)->assertOk();
        $this->getJson($this->path)->assertOk();

        $this->getJson($this->path)
            ->assertStatus(Response::HTTP_TOO_MANY_REQUESTS)
            ->assertJson([
                'message' => 'Too Many Requests',
            ]);
    }
}
