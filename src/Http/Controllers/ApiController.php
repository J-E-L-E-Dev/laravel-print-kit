<?php

declare(strict_types=1);

namespace Ahinest\LaravelPrintKit\Http\Controllers;

use Ahinest\LaravelPrintKit\Http\Middleware\ApiErrors;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Routing\Controller;

/** Captura errores antes de que el pipeline Laravel los entregue al handler global del consumidor. */
abstract class ApiController extends Controller
{
    public function callAction($method, $parameters)
    {
        $request = request();

        return app(ApiErrors::class)->handle($request, function () use ($method, $parameters, $request) {
            $result = parent::callAction($method, $parameters);

            return $result instanceof Responsable ? $result->toResponse($request) : $result;
        });
    }
}
