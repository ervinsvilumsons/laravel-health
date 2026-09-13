<?php

declare(strict_types=1);

namespace ErvinsVilumsons\LaravelHealth\Http\Controllers;

use Closure;
use ErvinsVilumsons\LaravelHealth\HealthManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;

class HealthController extends Controller
{
    public function __construct()
    {
        $this->middleware(function ($request, Closure $next): JsonResponse {
            /** @var JsonResponse $response */
            $response = $next($request);

            $response->headers->set('Content-Type', 'application/vnd.api+json');

            return $response;
        });
    }

    public function __invoke(HealthManager $healthManager): JsonResponse
    {
        return response()->json(
            $healthManager->getReport(),
            Response::HTTP_OK,
        );
    }
}
