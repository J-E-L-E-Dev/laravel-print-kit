<?php

declare(strict_types=1);

namespace Ahinest\LaravelPrintKit\Tests\Fixtures;

use Ahinest\LaravelPrintKit\Contracts\PrintKitModel;
use Ahinest\LaravelPrintKit\Models\Concerns\HasCanonicalColumns;
use Illuminate\Database\Eloquent\Model;

/** Sustituto que reutiliza el adaptador sin heredar ningún modelo del paquete. */
class IndependentPaperSize extends Model implements PrintKitModel
{
    use HasCanonicalColumns;

    protected $guarded = [];

    public function printKitEntity(): string
    {
        return 'paper_sizes';
    }

    protected $casts = ['width' => 'decimal:4', 'height' => 'decimal:4', 'is_active' => 'boolean'];
}
