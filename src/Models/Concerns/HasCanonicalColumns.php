<?php

declare(strict_types=1);

namespace Ahinest\LaravelPrintKit\Models\Concerns;

use Ahinest\LaravelPrintKit\Database\CanonicalBuilder;
use Ahinest\LaravelPrintKit\Database\CanonicalQueryBuilder;
use Ahinest\LaravelPrintKit\Support\ColumnResolver;
use Illuminate\Container\Container;

/** Conserva atributos canónicos en memoria y traduce únicamente al entrar o salir de SQL. */
trait HasCanonicalColumns
{
    protected function columnResolver(): ColumnResolver
    {
        return Container::getInstance()->make(ColumnResolver::class);
    }

    public function column(string $canonical): string
    {
        return $this->columnResolver()->column($this->printKitEntity(), $canonical);
    }

    public function getTable()
    {
        return $this->table ?? $this->columnResolver()->table($this->printKitEntity());
    }

    public function getConnectionName()
    {
        return $this->connection ?? Container::getInstance()->make('config')->get('print-kit.database.connection');
    }

    public function newEloquentBuilder($query)
    {
        return new CanonicalBuilder($query);
    }

    protected function newBaseQueryBuilder()
    {
        $connection = $this->getConnection();

        return new CanonicalQueryBuilder(
            $connection, $connection->getQueryGrammar(), $connection->getPostProcessor(),
            $this->columnResolver(), $this->printKitEntity(),
        );
    }

    public function setRawAttributes(array $attributes, $sync = false)
    {
        return parent::setRawAttributes($this->columnResolver()->toCanonical($this->printKitEntity(), $attributes), $sync);
    }
}
