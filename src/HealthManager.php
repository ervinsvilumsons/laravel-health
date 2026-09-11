<?php

declare(strict_types=1);

namespace ErvinsVilumsons\LaravelHealth;

use ErvinsVilumsons\LaravelHealth\Events\ServiceFailed;
use ErvinsVilumsons\LaravelHealth\Http\Resources\HealthResource;
use ErvinsVilumsons\LaravelHealth\Services\HealthService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use React\Promise\PromiseInterface;

use function React\Async\await;
use function React\Promise\all;

class HealthManager
{
    /** @var array<string, array{enabled?: bool, class: class-string, dependency?: string}> */
    public array $configuredServices = [];

    /** @var array<int, HealthService> */
    public array $healthServices = [];

    public function __construct()
    {
        /** @var array<string, array{enabled?: bool, class: class-string, dependency?: string}> $configuredServices */
        $configuredServices = Config::array('health-manager.services', []);
        $this->configuredServices = $configuredServices;

        $this->healthServices = [];
    }

    /**
     * @return array<int, HealthService>
     */
    private function getServices(): array
    {
        $this
            ->handleIndepententServices()
            ->handleDepententServices()
            ->handleFailedServices();

        usort(
            $this->healthServices,
            self::compareServices(...),
        );

        return $this->healthServices;
    }

    /**
     * @return array<string, mixed>
     */
    public function getReport(): array
    {
        return [
            'data' => [
                'id' => null,
                'type' => 'health-check',
                'attributes' => [
                    'timestamp' => Carbon::now(),
                    'services' => HealthResource::collection($this->getServices()),
                ],
            ],
        ];
    }

    private function handleFailedServices(): void
    {
        $context = [];
        $failedServices = array_filter(
            $this->healthServices,
            fn ($service): bool => $service->status() === HealthService::STATUS_DOWN,
        );

        foreach ($failedServices as $failedService) {
            $context[$failedService->name()] = $failedService->message();
        }

        $failedServicesNames = $failedServices
            |> (fn ($services): array => array_map(
                fn ($service): string => strtolower($service->name()),
                $services,
            ))
            |> (fn ($names): string => implode(', ', $names));

        if (! empty($context)) {
            ServiceFailed::dispatch(
                key: $failedServicesNames,
                title: 'Service Alert',
                message: 'Following services are down:',
                context: $context,
                level: 'error',
            );
        }
    }

    private function handleIndepententServices(): self
    {
        /** @var array<int, PromiseInterface<void>> $promises */
        $promises = [];

        $independentServices = array_filter(
            $this->configuredServices,
            static fn (mixed $service): bool => (bool) ($service['enabled'] ?? false) &&
                ! array_key_exists('dependency', $service)
        );

        foreach ($independentServices as $serviceConfig) {
            $service = $this->makeService($serviceConfig);
            $this->healthServices[] = $service;
            $promises[] = $service->statusAsync();
        }

        await(all($promises));

        return $this;
    }

    private function handleDepententServices(): self
    {
        /** @var array<int, PromiseInterface<void>> $promises */
        $promises = [];
        $dependentServices = array_filter(
            $this->configuredServices,
            static fn (mixed $service): bool => (bool) ($service['enabled'] ?? false) &&
                array_key_exists('dependency', $service)
        );
        $pending = array_keys($dependentServices);

        while ($pending !== []) {
            /** @var array<int, PromiseInterface<void>> $promises */
            $promises = [];

            $progress = false;

            foreach ($pending as $name) {
                $serviceConfig = $dependentServices[$name];
                $parentService = collect($this->healthServices)->first(fn ($service): bool => strtolower($service->name()) === strtolower($serviceConfig['dependency']));

                // Dependency hasn't completed yet.
                if ($parentService === null) {
                    continue;
                }

                $service = $this->makeService($serviceConfig);

                if ($parentService->status() === HealthService::STATUS_UP) {
                    $promises[] = $service->statusAsync();
                } else {
                    $service->skip($parentService->message() ?? "Dependency {$parentService->name()} is unavailable.");
                }

                $this->healthServices[] = $service;

                unset($pending[array_search($name, $pending, true)]);

                $progress = true;
            }

            if ($promises !== []) {
                await(all($promises));
            }

            // Avoid an infinite loop if a dependency cannot be resolved.
            if (! $progress) {
                break;
            }
        }

        return $this;
    }

    /**
     * @param  array{enabled?: bool, class: class-string, dependency?: string}  $serviceConfig
     */
    private function makeService(array $serviceConfig): HealthService
    {
        $service = app($serviceConfig['class']);

        if (! $service instanceof HealthService) {
            throw new \LogicException(
                sprintf(
                    'Health service [%s] must extend %s.',
                    $serviceConfig['class'],
                    HealthService::class,
                )
            );
        }

        return $service;
    }

    private static function compareServices(mixed $left, mixed $right): int
    {
        if (! $left instanceof HealthService || ! $right instanceof HealthService) {
            return 0;
        }

        return $left->name() <=> $right->name();
    }
}
