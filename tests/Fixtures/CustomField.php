<?php

declare(strict_types=1);

namespace Ahinest\LaravelPrintKit\Tests\Fixtures;

use Ahinest\LaravelPrintKit\Models\PrintField;

/** Demuestra que los modelos predeterminados admiten comportamiento adicional. */
class CustomField extends PrintField
{
    public function getDisplayNameAttribute($value): string
    {
        return strtoupper($value);
    }
}
