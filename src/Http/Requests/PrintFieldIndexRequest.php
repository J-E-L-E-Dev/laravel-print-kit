<?php

declare(strict_types=1);

namespace Ahinest\LaravelPrintKit\Http\Requests;

use Ahinest\LaravelPrintKit\Support\CatalogRules;

/** Valida filtros explícitos y límites de paginación. */
final class PrintFieldIndexRequest extends CatalogRequest
{
    public function rules(): array
    {
        return CatalogRules::pagination() + [
            'driver' => ['sometimes', 'string', 'max:100'],
            'system' => ['sometimes', 'string', 'size:3'],
            'category' => ['sometimes', 'string', 'max:2'],
            'usable_in_detail' => ['sometimes', 'boolean'],
        ];
    }
}
