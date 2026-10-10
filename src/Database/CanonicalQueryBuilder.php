<?php

declare(strict_types=1);

namespace Ahinest\LaravelPrintKit\Database;

use Ahinest\LaravelPrintKit\Support\ColumnResolver;
use Illuminate\Database\Query\Builder;

/** Traduce columnas de la API estructurada. Las expresiones SQL raw conservan su significado literal. */
class CanonicalQueryBuilder extends Builder
{
    public function __construct($connection, $grammar, $processor, private ColumnResolver $resolver, private string $entity)
    {
        parent::__construct($connection, $grammar, $processor);
    }

    public function newQuery()
    {
        return new static($this->connection, $this->grammar, $this->processor, $this->resolver, $this->entity);
    }

    private function mapColumn(mixed $column): mixed
    {
        return $this->resolver->queryColumn($this->entity, $column, is_string($this->from) ? $this->from : null);
    }

    private function mapColumns(array $columns): array
    {
        return array_map(fn ($column) => $this->mapColumn($column), $columns);
    }

    public function select($columns = ['*'])
    {
        return parent::select($this->mapColumns(is_array($columns) ? $columns : func_get_args()));
    }

    public function addSelect($column)
    {
        return parent::addSelect($this->mapColumns(is_array($column) ? $column : func_get_args()));
    }

    public function where($column, $operator = null, $value = null, $boolean = 'and')
    {
        if (is_array($column) || $column instanceof \Closure) {
            return parent::where($column, $operator, $value, $boolean);
        }
        $args = func_get_args();
        $args[0] = $this->mapColumn($column);

        return parent::where(...$args);
    }

    public function whereColumn($first, $operator = null, $second = null, $boolean = 'and')
    {
        if (is_array($first)) {
            return parent::whereColumn($first, $operator, $second, $boolean);
        }
        if (func_num_args() === 2) {
            return parent::whereColumn($this->mapColumn($first), $this->mapColumn($operator));
        }

        return parent::whereColumn($this->mapColumn($first), $operator, $this->mapColumn($second), $boolean);
    }

    public function groupBy(...$groups)
    {
        return parent::groupBy(...array_map(fn ($group) => is_array($group) ? $this->mapColumns($group) : $this->mapColumn($group), $groups));
    }

    public function insert(array $values)
    {
        return parent::insert($this->mapRows($values));
    }

    public function insertOrIgnore(array $values)
    {
        return parent::insertOrIgnore($this->mapRows($values));
    }

    private function mapRows(array $values): array
    {
        if ($values === []) {
            return [];
        }

        return is_array(reset($values))
            ? array_map(fn ($row) => $this->resolver->toPhysical($this->entity, $row), $values)
            : $this->resolver->toPhysical($this->entity, $values);
    }

    public function insertGetId(array $values, $sequence = null)
    {
        return parent::insertGetId($this->resolver->toPhysical($this->entity, $values), $this->mapColumn($sequence));
    }

    public function update(array $values)
    {
        return parent::update($this->resolver->toPhysical($this->entity, $values, is_string($this->from) ? $this->from : null));
    }

    public function upsert(array $values, $uniqueBy, $update = null)
    {
        return parent::upsert($this->mapRows($values), $this->mapColumns((array) $uniqueBy), $update === null ? null : $this->mapColumns($update));
    }

    public function incrementEach(array $columns, array $extra = [])
    {
        return parent::incrementEach($this->resolver->toPhysical($this->entity, $columns), $this->resolver->toPhysical($this->entity, $extra));
    }

    public function decrementEach(array $columns, array $extra = [])
    {
        return parent::decrementEach($this->resolver->toPhysical($this->entity, $columns), $this->resolver->toPhysical($this->entity, $extra));
    }

    public function get($columns = ['*'])
    {
        return parent::get($this->mapColumns((array) $columns));
    }

    public function cursor($columns = ['*'])
    {
        if ($this->columns === null) {
            $this->select($columns);
        }

        return parent::cursor();
    }

    public function pluck($column, $key = null)
    {
        return parent::pluck($this->mapColumn($column), $this->mapColumn($key));
    }

    public function whereIn($column, $values, $boolean = 'and', $not = false)
    {
        return parent::whereIn($this->mapColumn($column), $values, $boolean, $not);
    }

    public function whereIntegerInRaw($column, $values, $boolean = 'and', $not = false)
    {
        return parent::whereIntegerInRaw($this->mapColumn($column), $values, $boolean, $not);
    }

    public function whereNull($columns, $boolean = 'and', $not = false)
    {
        return parent::whereNull($this->mapColumns((array) $columns), $boolean, $not);
    }

    public function whereBetween($column, iterable $values, $boolean = 'and', $not = false)
    {
        return parent::whereBetween($this->mapColumn($column), $values, $boolean, $not);
    }

    public function whereBetweenColumns($column, array $values, $boolean = 'and', $not = false)
    {
        return parent::whereBetweenColumns($this->mapColumn($column), $this->mapColumns($values), $boolean, $not);
    }

    protected function addDateBasedWhere($type, $column, $operator, $value, $boolean = 'and')
    {
        return parent::addDateBasedWhere($type, $this->mapColumn($column), $operator, $value, $boolean);
    }

    /** Conserva los alias de la API nativa moderna; Laravel 10 utiliza LIKE según su collation. */
    public function whereLike($column, $value, $caseSensitive = false, $boolean = 'and', $not = false)
    {
        if (method_exists(Builder::class, 'whereLike')) {
            return parent::whereLike($this->mapColumn($column), $value, $caseSensitive, $boolean, $not);
        }
        if ($caseSensitive) {
            throw new \LogicException('Este framework no permite solicitar LIKE sensible a mayúsculas mediante esta API.');
        }

        return $this->where($column, $not ? 'not like' : 'like', $value, $boolean);
    }

    public function whereJsonContains($column, $value, $boolean = 'and', $not = false)
    {
        return parent::whereJsonContains($this->mapColumn($column), $value, $boolean, $not);
    }

    public function whereJsonContainsKey($column, $boolean = 'and', $not = false)
    {
        return parent::whereJsonContainsKey($this->mapColumn($column), $boolean, $not);
    }

    public function whereJsonLength($column, $operator, $value = null, $boolean = 'and')
    {
        $args = func_get_args();
        $args[0] = $this->mapColumn($column);

        return parent::whereJsonLength(...$args);
    }

    public function orderBy($column, $direction = 'asc')
    {
        return parent::orderBy($this->mapColumn($column), $direction);
    }

    public function having($column, $operator = null, $value = null, $boolean = 'and')
    {
        $args = func_get_args();
        $args[0] = $this->mapColumn($column);

        return parent::having(...$args);
    }

    public function aggregate($function, $columns = ['*'])
    {
        return parent::aggregate($function, $this->mapColumns($columns));
    }
}
