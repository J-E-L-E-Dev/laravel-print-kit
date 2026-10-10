<?php

declare(strict_types=1);

namespace Ahinest\LaravelPrintKit\Http\Requests;

use Ahinest\LaravelPrintKit\Support\CatalogRules;

/** Valida filtros explícitos y límites de paginación. */
final class PaperSizeIndexRequest extends CatalogRequest
{
    public function rules(): array
    {
        return CatalogRules::pagination() + ['is_active' => ['sometimes', 'boolean']];
    }
}
