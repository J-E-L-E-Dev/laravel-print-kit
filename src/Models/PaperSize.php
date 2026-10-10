<?php

declare(strict_types=1);

namespace Ahinest\LaravelPrintKit\Models;

use Ahinest\LaravelPrintKit\Support\CatalogRules;
use Ahinest\LaravelPrintKit\Support\ModelResolver;
use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Representa dimensiones en centímetros con cuatro decimales y admite tamaños personalizados. */
class PaperSize extends PrintKitModel
{
    protected $fillable = ['name', 'description', 'width', 'height', 'is_active'];

    public function printKitEntity(): string
    {
        return 'paper_sizes';
    }

    protected $casts = ['width' => 'decimal:4', 'height' => 'decimal:4', 'is_active' => 'boolean'];

    public function formats(): HasMany
    {
        $related = Container::getInstance()->make(ModelResolver::class)->make('format');

        return $this->hasMany($related::class, 'paper_size_id', 'id');
    }

    protected function rules(): array
    {
        return CatalogRules::paper();
    }
}
