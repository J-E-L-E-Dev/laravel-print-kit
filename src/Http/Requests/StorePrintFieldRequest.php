<?php

declare(strict_types=1);

namespace Ahinest\LaravelPrintKit\Http\Requests;

use Ahinest\LaravelPrintKit\Support\CatalogRules;

/** Valida los atributos editables del catálogo. */
final class StorePrintFieldRequest extends CatalogRequest
{
    public function rules(): array
    {
        return CatalogRules::write('fields', false);
    }
}
