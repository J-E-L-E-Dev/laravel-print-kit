<?php

declare(strict_types=1);

namespace Ahinest\LaravelPrintKit\Http\Resources;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Ordena por etiqueta sin depender de inserción ni collation; el valor desambigua empates. */
final class OptionResponse
{
    public static function make(array $items, string $resource, Request $request): JsonResponse
    {
        $rows = array_map(fn ($item) => (new $resource($item))->toArray($request), array_values($items));
        usort($rows, static fn ($a, $b) => strcmp(mb_strtolower((string) $a['label'], 'UTF-8'), mb_strtolower((string) $b['label'], 'UTF-8')) ?: strcmp((string) $a['value'], (string) $b['value']));

        return new JsonResponse(['data' => $rows]);
    }
}
