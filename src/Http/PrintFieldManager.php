<?php

declare(strict_types=1);

namespace Ahinest\LaravelPrintKit\Http;

use Ahinest\LaravelPrintKit\Contracts\PrintKitModel;
use Ahinest\LaravelPrintKit\Data\DataType;
use Ahinest\LaravelPrintKit\Data\Exceptions\InvalidDataType;
use Ahinest\LaravelPrintKit\Data\Exceptions\UnknownCategory;
use Ahinest\LaravelPrintKit\Data\Registries\CategoryRegistry;
use Ahinest\LaravelPrintKit\Data\Registries\DriverRegistry;
use Ahinest\LaravelPrintKit\Data\Registries\SystemRegistry;
use Ahinest\LaravelPrintKit\Support\CatalogRules;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;

/** Administra definiciones sin abrir conexiones de origen y conserva la identidad literal. */
final class PrintFieldManager extends CatalogManager
{
    private const IDENTITY = ['driver', 'system', 'category', 'placeholder'];

    protected function modelName(): string
    {
        return 'field';
    }

    protected function rules(): array
    {
        return CatalogRules::field();
    }

    private function context(array $data): void
    {
        if (isset($data['driver'])) {
            app(DriverRegistry::class)->get($data['driver']);
        }
        $system = isset($data['system']) ? app(SystemRegistry::class)->get($data['system']) : null;
        if (isset($data['category']) && $data['category'] !== '*') {
            app(CategoryRegistry::class)->get($data['category']);
            if ($system && ! in_array($data['category'], $system->categories, true)) {
                throw new UnknownCategory('Categoría no habilitada para el sistema.');
            }
        }
    }

    protected function validate(array $attributes): void
    {
        parent::validate($attributes);
        $this->context($attributes);
        try {
            DataType::parse($attributes['data_type']);
        } catch (InvalidDataType $exception) {
            throw ValidationException::withMessages(['data_type' => [$exception->getMessage()]]);
        }
    }

    public function list(array $filters): mixed
    {
        $this->context($filters);
        $query = $this->models->make('field')->newQuery();
        foreach (['driver', 'system', 'category', 'usable_in_detail'] as $key) {
            if (array_key_exists($key, $filters)) {
                $query->where($key, $filters[$key]);
            }
        }
        if (isset($filters['search'])) {
            $search = '%'.$filters['search'].'%';
            $query->where(fn ($q) => $q->where('placeholder', 'like', $search)->orWhere('display_name', 'like', $search)
                ->orWhere('description', 'like', $search)->orWhere('associated_field', 'like', $search));
        }
        foreach (['driver', 'system', 'category', 'display_name', 'id'] as $key) {
            $query->orderBy($key);
        }

        return $this->paginate($query, $filters);
    }

    private function duplicate(): never
    {
        throw ValidationException::withMessages(['placeholder' => ['Ya existe un campo con este driver, sistema, categoría y placeholder.']]);
    }

    protected function persist(Model&PrintKitModel $model): void
    {
        $query = $model->newQueryWithoutScopes();
        foreach (self::IDENTITY as $key) {
            $query->where($key, $model->getAttribute($key));
        }
        if ($model->exists) {
            $query->where('id', '!=', $model->getAttribute('id'));
        }
        // Confirma identidad estricta incluso si el almacenamiento usa collation insensible.
        foreach ($query->get() as $candidate) {
            $same = true;
            foreach (self::IDENTITY as $key) {
                $same = $same && $candidate->getAttribute($key) === $model->getAttribute($key);
            }
            if ($same) {
                $this->duplicate();
            }
        }
        try {
            parent::persist($model);
        } catch (QueryException $exception) {
            $info = $exception->errorInfo;
            if (($info[0] ?? '') === '23505' || in_array((int) ($info[1] ?? 0), [1062, 2601, 2627], true)
                || str_contains(strtolower($info[2] ?? ''), 'unique constraint')) {
                $this->duplicate();
            }
            throw $exception;
        }
    }
}
