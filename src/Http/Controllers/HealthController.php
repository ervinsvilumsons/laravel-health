<?php

declare(strict_types=1);

namespace ErvinsVilumsons\LaravelHealth\Http\Controllers;

use ErvinsVilumsons\LaravelHealth\HealthManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;

class HealthController extends Controller
{
    public function __invoke(HealthManager $healthManager): JsonResponse
    {
        return response()->json(
            $healthManager->getReport(),
            Response::HTTP_OK,
            [
                'Content-Type' => 'application/vnd.api+json',
            ],
        );
    }
}
