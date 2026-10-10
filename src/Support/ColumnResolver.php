<?php

declare(strict_types=1);

namespace Ahinest\LaravelPrintKit\Support;

use Illuminate\Contracts\Config\Repository;
use InvalidArgumentException;

/** Traduce identificadores; nunca interpreta expresiones SQL proporcionadas por usuarios. */
class ColumnResolver
{
    public function __construct(private Repository $config) {}

    public function table(string $entity): string
    {
        return $this->identifier($this->config->get("print-kit.tables.$entity"));
    }

    public function map(string $entity): array
    {
        $map = $this->config->get("print-kit.columns.$entity", []);
        $physical = [];
        foreach ($map as $canonical => $column) {
            $this->identifier($canonical);
            $this->identifier($column);
            if (in_array($column, $physical, true) || ($column !== $canonical && array_key_exists($column, $map))) {
                throw new InvalidArgumentException("Mapa de columnas ambiguo para $entity.");
            }
            $physical[] = $column;
        }

        return $map;
    }

    public function column(string $entity, string $canonical): string
    {
        return $this->map($entity)[$canonical] ?? $canonical;
    }

    /** Reconoce columnas calificadas de otras entidades en consultas de relaciones. */
    public function queryColumn(string $entity, mixed $column, ?string $currentTable = null): mixed
    {
        if (! is_string($column)) {
            return $column;
        }
        if (preg_match('/^(.+?)\\s+as\\s+([a-zA-Z_][a-zA-Z0-9_]*)$/i', $column, $parts)) {
            return $this->queryColumn($entity, $parts[1], $currentTable).' as '.$parts[2];
        }
        $json = explode('->', $column, 2);
        $parts = explode('.', $json[0]);
        if (count($parts) === 2) {
            $known = $parts[0] === $currentTable;
            foreach ($this->config->get('print-kit.tables', []) as $candidate => $table) {
                if ($table === $parts[0]) {
                    $entity = $candidate;
                    $known = true;
                    break;
                }
            }
            if (! $known) {
                return $column;
            }
            $mapped = $parts[0].'.'.$this->column($entity, $parts[1]);
        } else {
            $mapped = $this->column($entity, $json[0]);
        }

        return $mapped.(isset($json[1]) ? '->'.$json[1] : '');
    }

    public function toCanonical(string $entity, array $attributes): array
    {
        $reverse = array_flip($this->map($entity));
        $result = [];
        foreach ($attributes as $key => $value) {
            $result[$reverse[$key] ?? $key] = $value;
        }

        return $result;
    }

    public function toPhysical(string $entity, array $attributes, ?string $currentTable = null): array
    {
        $result = [];
        foreach ($attributes as $key => $value) {
            $result[$this->queryColumn($entity, $key, $currentTable)] = $value;
        }

        return $result;
    }

    private function identifier(mixed $value): string
    {
        if (! is_string($value) || ! preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/D', $value)) {
            throw new InvalidArgumentException('Las tablas y columnas configuradas deben ser identificadores simples.');
        }

        return $value;
    }
}
