<?php

declare(strict_types=1);

namespace Ahinest\LaravelPrintKit\Tests\Fixtures;

use Ahinest\LaravelPrintKit\Models\PrintField;

/** Comprueba que el validador respeta también los scopes del modelo configurado. */
class ScopedField extends PrintField
{
    protected static function booted(): void
    {
        static::addGlobalScope('allowed', static fn ($query) => $query->where('description', 'allowed'));
    }
}
