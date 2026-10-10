<?php

declare(strict_types=1);

namespace Ahinest\LaravelPrintKit\Data;

use Ahinest\LaravelPrintKit\Contracts\PrintKitModel;
use Illuminate\Database\Eloquent\Model;

/** Ofrece los tres modos sin incorporar renderizado ni persistencia de resultados. */
final class LabelResolver
{
    public function __construct(private ProvidedValuesResolver $values, private ProvidedRecordResolver $records, private DatabaseDataResolver $database) {}

    public function resolveWithValues(Model&PrintKitModel $format, array $values): ResolvedLabel
    {
        return $this->values->resolve($format, $values);
    }

    public function resolveWithRecord(Model&PrintKitModel $format, string $driver, mixed $record): ResolvedLabel
    {
        return $this->records->resolve($format, $driver, $record);
    }

    public function resolveByKey(Model&PrintKitModel $format, string $driver, mixed $key): ResolvedLabel
    {
        return $this->database->resolveByKey($format, $driver, $key);
    }

    public function resolveByKeys(Model&PrintKitModel $format, string $driver, array $keys): ResolvedLabelCollection
    {
        return $this->database->resolveByKeys($format, $driver, $keys);
    }
}
