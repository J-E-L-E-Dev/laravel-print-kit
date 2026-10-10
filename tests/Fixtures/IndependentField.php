<?php

declare(strict_types=1);

namespace Ahinest\LaravelPrintKit\Tests\Fixtures;

use Ahinest\LaravelPrintKit\Contracts\PrintKitModel;
use Ahinest\LaravelPrintKit\Models\Concerns\HasCanonicalColumns;
use Illuminate\Database\Eloquent\Model;

/** Sustituye el catálogo sin heredar el modelo PrintField del paquete. */
class IndependentField extends Model implements PrintKitModel
{
    use HasCanonicalColumns;

    protected $guarded = [];

    protected $casts = ['usable_in_detail' => 'boolean'];

    public function printKitEntity(): string
    {
        return 'fields';
    }
}
