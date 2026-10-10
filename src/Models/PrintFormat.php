<?php

declare(strict_types=1);

namespace Ahinest\LaravelPrintKit\Models;

use Ahinest\LaravelPrintKit\Design\InvalidDesign;
use Ahinest\LaravelPrintKit\Design\LabelDesign;
use Ahinest\LaravelPrintKit\Support\ModelResolver;
use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Persiste etiquetas con diseño versionado y valida su contexto antes de guardar. */
class PrintFormat extends PrintKitModel
{
    protected $fillable = ['code', 'name', 'copies', 'preview', 'printer', 'paper_size_id', 'orientation', 'system', 'detail', 'category', 'version', 'margins', 'use_default_printer', 'disabled', 'parameters'];

    public function printKitEntity(): string
    {
        return 'formats';
    }

    protected $casts = [
        'copies' => 'integer', 'preview' => 'boolean', 'detail' => 'array',
        'version' => 'integer', 'margins' => 'array',
        'use_default_printer' => 'boolean', 'disabled' => 'boolean', 'parameters' => 'array',
    ];

    public function paperSize(): BelongsTo
    {
        $related = Container::getInstance()->make(ModelResolver::class)->make('paper_size');

        return $this->belongsTo($related::class, 'paper_size_id', 'id');
    }

    public function design(): LabelDesign
    {
        if (! is_array($this->detail)) {
            throw new InvalidDesign('detail', 'Debe cargar la definición completa del diseño.');
        }

        return LabelDesign::fromArray($this->detail);
    }

    /** Asigna un diseño válido para el contexto actual; el guardado sigue siendo explícito. */
    public function setDesign(LabelDesign $design): static
    {
        $design->validateFor($this);
        $this->detail = $design->toArray();

        return $this;
    }

    public function setAttribute($key, $value)
    {
        if ($key === 'detail' && is_array($value)) {
            $value = LabelDesign::fromArray($value)->toArray();
        }

        return parent::setAttribute($key, $value);
    }

    public function save(array $options = [])
    {
        $this->design()->validateFor($this);

        return parent::save($options);
    }

    protected function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:100'],
            'name' => ['required', 'string', 'max:255'],
            'copies' => ['required', 'integer', 'min:1', 'max:2147483647'],
            'preview' => ['required', 'boolean'],
            'printer' => ['nullable', 'string', 'max:255'],
            'paper_size_id' => ['required', 'integer', 'min:1'],
            'orientation' => ['required', 'in:V,H'],
            'system' => ['required', 'string', 'size:3'],
            'category' => ['required', 'string', 'size:2'],
            'version' => ['required', 'integer', 'min:1', 'max:2147483647'],
            'detail' => ['present', 'array'],
            'margins' => ['required', 'array:top,right,bottom,left'],
            'margins.top' => ['required_with:margins', 'numeric', 'min:0'],
            'margins.right' => ['required_with:margins', 'numeric', 'min:0'],
            'margins.bottom' => ['required_with:margins', 'numeric', 'min:0'],
            'margins.left' => ['required_with:margins', 'numeric', 'min:0'],
            'use_default_printer' => ['required', 'boolean'],
            'disabled' => ['required', 'boolean'],
            'parameters' => ['nullable', 'array'],
        ];
    }
}
