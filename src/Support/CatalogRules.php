<?php

declare(strict_types=1);

namespace Ahinest\LaravelPrintKit\Support;

/** Comparte el contrato estructural entre modelos, managers y validación HTTP. */
final class CatalogRules
{
    public static function paper(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'width' => ['required', 'numeric', 'min:0.0001', 'max:999999.9999', 'decimal:0,4'],
            'height' => ['required', 'numeric', 'min:0.0001', 'max:999999.9999', 'decimal:0,4'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    public static function field(): array
    {
        return [
            'driver' => ['required', 'string', 'max:100', 'regex:/^[a-zA-Z0-9_-]+$/D'],
            'system' => ['required', 'string', 'regex:/^[A-Z][A-Z0-9]{2}$/D'],
            'category' => ['required', 'string', 'regex:/^(?:[A-Z][A-Z0-9]|\\*)$/D'],
            'placeholder' => ['required', 'string', 'max:100', 'regex:/^[a-zA-Z_][a-zA-Z0-9_]*$/D'],
            'display_name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'data_type' => ['required', 'string', 'max:32'],
            'usable_in_detail' => ['required', 'boolean'],
            'associated_field' => ['required', 'string', 'max:128', 'regex:/^[a-zA-Z_][a-zA-Z0-9_]*$/D'],
        ];
    }

    public static function write(string $entity, bool $partial): array
    {
        $rules = $entity === 'paper_sizes' ? self::paper() : self::field();
        $defaults = config("print-kit.defaults.$entity", []);
        foreach ($rules as $key => &$rule) {
            if ($partial || array_key_exists($key, $defaults)) {
                array_unshift($rule, 'sometimes');
            }
        }

        return $rules;
    }

    public static function pagination(): array
    {
        return [
            'search' => ['sometimes', 'string', 'max:255'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:'.max(1, (int) config('print-kit.api.max_per_page', 100))],
        ];
    }
}
