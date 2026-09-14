<?php

declare(strict_types=1);

namespace ErvinsVilumsons\LaravelHealth\Http\Middleware;

use Closure;
use ErvinsVilumsons\LaravelHealth\Support\RateLimiter;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final readonly class Throttle
{
    public function __construct(private RateLimiter $rateLimiter) {}

    public function handle(
        Request $request,
        Closure $next,
    ): mixed {
        $key = $this->key($request);

        if (! $this->rateLimiter->attempt($key)) {
            return response()->json([
                'message' => 'Too Many Requests',
            ], Response::HTTP_TOO_MANY_REQUESTS, [
                'Retry-After' => (string) $this->rateLimiter->retryAfter($key),
            ]);
        }

        return $next($request);
    }

    private function key(Request $request): string
    {
        return $request->ip() ?? 'unknown';
    }
}
