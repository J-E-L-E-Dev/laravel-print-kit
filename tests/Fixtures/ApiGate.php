<?php

declare(strict_types=1);

namespace Ahinest\LaravelPrintKit\Tests\Fixtures;

use Illuminate\Http\JsonResponse;

/** Middleware de prueba que representa la autorización decidida por el consumidor. */
class ApiGate
{
    public function handle($request, \Closure $next)
    {
        if ($request->header('X-Allow') !== 'yes') {
            return new JsonResponse(['message' => 'Denied'], 403);
        }

        return $next($request);
    }
}
