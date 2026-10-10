<?php

declare(strict_types=1);

namespace Ahinest\LaravelPrintKit\Http;

use Ahinest\LaravelPrintKit\Contracts\PrintKitModel;
use Ahinest\LaravelPrintKit\Support\ModelResolver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/** Comparte persistencia canónica y validación completa para los dos catálogos administrativos. */
abstract class CatalogManager
{
    public function __construct(protected ModelResolver $models) {}

    abstract protected function modelName(): string;

    abstract protected function rules(): array;

    public function find(string $id): Model&PrintKitModel
    {
        return $this->models->make($this->modelName())->newQuery()->where('id', $id)->first()
            ?? throw new NotFoundHttpException;
    }

    public function create(array $attributes): Model&PrintKitModel
    {
        $model = $this->models->make($this->modelName());
        $defaults = config('print-kit.defaults.'.$model->printKitEntity(), []);

        return $this->write($model, $attributes + array_intersect_key($defaults, $this->rules()));
    }

    public function update(Model&PrintKitModel $model, array $attributes): Model&PrintKitModel
    {
        return $this->write($model, $attributes);
    }

    protected function validate(array $attributes): void
    {
        Validator::make($attributes, $this->rules())->validate();
    }

    private function write(Model&PrintKitModel $model, array $attributes): Model&PrintKitModel
    {
        $rules = $this->rules();
        $unknown = array_diff(array_keys($attributes), array_keys($rules));
        if ($unknown !== []) {
            throw ValidationException::withMessages(array_fill_keys($unknown, ['Propiedad no editable.']));
        }
        $current = [];
        foreach (array_keys($rules) as $key) {
            $current[$key] = $model->getAttribute($key);
        }
        $this->validate(array_replace($current, $attributes));
        foreach ($attributes as $key => $value) {
            $model->setAttribute($key, $value);
        }
        $this->persist($model);

        return $model;
    }

    protected function persist(Model&PrintKitModel $model): void
    {
        if (! $model->save()) {
            throw ValidationException::withMessages(['catalog' => ['El modelo rechazó el guardado.']]);
        }
    }

    protected function paginate($query, array $filters): mixed
    {
        $limit = max(1, (int) config('print-kit.api.max_per_page', 100));
        $perPage = $filters['per_page'] ?? min(max(1, (int) config('print-kit.api.per_page', 25)), $limit);

        return $query->paginate($perPage, ['*'], 'page', $filters['page'] ?? 1)->withQueryString();
    }
}
