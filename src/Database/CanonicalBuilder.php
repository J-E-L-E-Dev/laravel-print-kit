<?php

declare(strict_types=1);

namespace Ahinest\LaravelPrintKit\Database;

use Ahinest\LaravelPrintKit\Contracts\PrintKitModel as PrintKitModelContract;
use Ahinest\LaravelPrintKit\Models\PrintKitModel;
use Illuminate\Database\Eloquent\Builder;
use LogicException;

/** Aplica casts y validación a actualizaciones masivas con atributos canónicos. */
class CanonicalBuilder extends Builder
{
    public function update(array $values)
    {
        if ($this->model instanceof PrintKitModel && $this->model->isSavingCanonicalAttributes()) {
            return parent::update($values);
        }
        if ($this->model instanceof PrintKitModelContract && $this->model->printKitEntity() === 'formats') {
            $protected = ['detail', 'paper_size_id', 'orientation', 'margins', 'system', 'category'];
            $protected = array_merge($protected, array_map(fn ($column) => $this->model->column($column), $protected));
            foreach (array_keys($values) as $key) {
                $parts = explode('.', explode('->', $key)[0]);
                $attribute = end($parts);
                if (in_array($attribute, $protected, true)) {
                    throw new LogicException('El diseño y su contexto requieren actualizar una instancia y guardar con validación.');
                }
            }
        }

        $model = $this->model->newInstance();
        foreach ($values as $key => $value) {
            $model->setAttribute($key, $value);
        }

        return parent::update(array_intersect_key($model->getAttributes(), $values));
    }

    /** Las operaciones aritméticas omiten eventos y validación en Eloquent; se rechazan en esta fase. */
    public function increment($column, $amount = 1, array $extra = [])
    {
        throw new LogicException('Use fill() y save() para modificar cantidades con validación.');
    }

    public function decrement($column, $amount = 1, array $extra = [])
    {
        throw new LogicException('Use fill() y save() para modificar cantidades con validación.');
    }

    public function incrementEach(array $columns, array $extra = [])
    {
        throw new LogicException('Use fill() y save() para modificar cantidades con validación.');
    }

    public function decrementEach(array $columns, array $extra = [])
    {
        throw new LogicException('Use fill() y save() para modificar cantidades con validación.');
    }

    public function upsert(array $values, $uniqueBy, $update = null)
    {
        throw new LogicException('Use updateOrCreate() para conservar casts y validación.');
    }
}
