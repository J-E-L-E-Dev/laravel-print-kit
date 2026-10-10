<?php

declare(strict_types=1);

namespace Ahinest\LaravelPrintKit\Tests\Fixtures;

use Ahinest\LaravelPrintKit\Contracts\PrintKitModel;
use Ahinest\LaravelPrintKit\Models\Concerns\HasCanonicalColumns;
use Illuminate\Database\Eloquent\Model;

/** Sustituye el formato completo para probar el contrato del validador de dominio. */
class IndependentFormat extends Model implements PrintKitModel
{
    use HasCanonicalColumns;

    protected $guarded = [];

    protected $casts = ['detail' => 'array', 'margins' => 'array'];

    public function printKitEntity(): string
    {
        return 'formats';
    }
}
