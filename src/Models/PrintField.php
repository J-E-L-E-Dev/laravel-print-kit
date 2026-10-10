<?php

declare(strict_types=1);

namespace Ahinest\LaravelPrintKit\Models;

use Ahinest\LaravelPrintKit\Data\DataType;
use Ahinest\LaravelPrintKit\Support\CatalogRules;

/** Describe un comodín; driver identifica su origen, no la conexión de almacenamiento. */
class PrintField extends PrintKitModel
{
    protected $fillable = ['driver', 'system', 'category', 'placeholder', 'display_name', 'description', 'data_type', 'usable_in_detail', 'associated_field'];

    public function printKitEntity(): string
    {
        return 'fields';
    }

    protected $casts = ['usable_in_detail' => 'boolean'];

    /** Aplica el contrato compacto también a asignaciones y actualizaciones parciales. */
    public function validateStructure(array $attributes, bool $partial = false): void
    {
        if (array_key_exists('data_type', $attributes)) {
            DataType::parse($attributes['data_type']);
        }
        parent::validateStructure($attributes, $partial);
    }

    protected function rules(): array
    {
        return CatalogRules::field();
    }
}
