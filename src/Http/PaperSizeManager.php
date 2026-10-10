<?php

declare(strict_types=1);

namespace Ahinest\LaravelPrintKit\Http;

use Ahinest\LaravelPrintKit\Contracts\PrintKitModel;
use Ahinest\LaravelPrintKit\Support\CatalogRules;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/** Administra papeles y protege referencias globales usando modelos y columnas configurados. */
final class PaperSizeManager extends CatalogManager
{
    protected function modelName(): string
    {
        return 'paper_size';
    }

    protected function rules(): array
    {
        return CatalogRules::paper();
    }

    public function list(array $filters): mixed
    {
        $query = $this->models->make('paper_size')->newQuery();
        if (array_key_exists('is_active', $filters)) {
            $query->where('is_active', $filters['is_active']);
        }
        if (isset($filters['search'])) {
            $search = '%'.$filters['search'].'%';
            $query->where(fn ($q) => $q->where('name', 'like', $search)->orWhere('description', 'like', $search));
        }

        return $this->paginate($query->orderBy('name')->orderBy('id'), $filters);
    }

    public function delete(Model&PrintKitModel $paper): void
    {
        try {
            $paper->getConnection()->transaction(function () use ($paper): void {
                // Revalida visibilidad dentro de la transacción; la FK protege inserciones concurrentes.
                $locked = $paper->newQuery()->where('id', $paper->getAttribute('id'))->lockForUpdate()->first()
                    ?? throw new NotFoundHttpException;
                $format = $this->models->make('format');
                if ($format->getConnectionName() === null) {
                    $format->setConnection($paper->getConnectionName());
                }
                if ($format->newQueryWithoutScopes()->where('paper_size_id', $paper->getAttribute('id'))->exists()) {
                    throw new ConflictHttpException;
                }
                if (! $locked->delete()) {
                    throw ValidationException::withMessages(['paper_size' => ['El modelo rechazó la eliminación.']]);
                }
            });
        } catch (QueryException $exception) {
            $info = $exception->errorInfo;
            if (($info[0] ?? '') === '23503' || in_array((int) ($info[1] ?? 0), [1451, 547], true)
                || str_contains(strtolower($info[2] ?? ''), 'foreign key constraint')) {
                throw new ConflictHttpException;
            }
            throw $exception;
        }
    }
}
