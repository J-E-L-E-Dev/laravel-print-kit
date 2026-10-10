<?php

declare(strict_types=1);

namespace Ahinest\LaravelPrintKit\Models;

use Ahinest\LaravelPrintKit\Contracts\PrintKitModel as PrintKitModelContract;
use Ahinest\LaravelPrintKit\Models\Concerns\HasCanonicalColumns;
use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Model;

/** Aporta valores iniciales y validación estructural a los modelos predeterminados. */
abstract class PrintKitModel extends Model implements PrintKitModelContract
{
    use HasCanonicalColumns;

    private bool $savingCanonicalAttributes = false;

    public function __construct(array $attributes = [])
    {
        parent::__construct();
        $defaults = Container::getInstance()->make('config')->get('print-kit.defaults.'.$this->printKitEntity(), []);
        foreach ($defaults as $key => $value) {
            $this->setAttribute($key, $value);
        }
        $this->syncOriginal();
        $this->fill($attributes);
    }

    /** Valida antes de aplicar casts que podrían ocultar valores inválidos como copies = 1.5. */
    public function setAttribute($key, $value)
    {
        $this->validateStructure([$key => $value], true);

        return parent::setAttribute($key, $value);
    }

    public function save(array $options = [])
    {
        $attributes = [];
        foreach (array_keys($this->getAttributes()) as $key) {
            $attributes[$key] = $this->getAttributeValue($key);
        }
        $this->validateStructure($attributes, $this->exists);
        $this->savingCanonicalAttributes = true;

        try {
            return parent::save($options);
        } finally {
            $this->savingCanonicalAttributes = false;
        }
    }

    /** Identifica el guardado de Eloquent, cuyos atributos ya están serializados. */
    public function isSavingCanonicalAttributes(): bool
    {
        return $this->savingCanonicalAttributes;
    }

    /** En actualizaciones parciales solo exige las propiedades presentes. */
    public function validateStructure(array $attributes, bool $partial = false): void
    {
        $rules = $this->rules();
        if ($partial) {
            $rules = array_filter($rules, fn ($key) => array_key_exists(explode('.', $key)[0], $attributes), ARRAY_FILTER_USE_KEY);
        }
        Container::getInstance()->make('validator')->make($attributes, $rules)->validate();
    }

    abstract protected function rules(): array;
}
