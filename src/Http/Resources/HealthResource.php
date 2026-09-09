<?php

declare(strict_types=1);

namespace ErvinsVilumsons\LaravelHealth\Http\Resources;

use ErvinsVilumsons\LaravelHealth\Services\HealthService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read HealthService $resource
 */
class HealthResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'name' => $this->resource->name(),
            'connection' => $this->resource->connection(),
            'status' => $this->resource->status(),
            'message' => $this->resource->message(),
            'responseTime' => $this->resource->responseTime(),
        ];
    }
}
