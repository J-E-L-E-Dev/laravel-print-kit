<?php

declare(strict_types=1);

namespace Ahinest\LaravelPrintKit\Data;

use Ahinest\LaravelPrintKit\Data\Exceptions\AssociatedFieldNotFound;
use Illuminate\Database\Eloquent\Model;

/** Lee atributos presentes, incluido NULL, sin activar relaciones Eloquent. */
final class RecordAccessor
{
    public function get(mixed $record, string $column): mixed
    {
        if ($record instanceof Model) {
            if (array_key_exists($column, $record->getAttributes())) {
                return $record->getAttributeValue($column);
            }
        } elseif (is_array($record)) {
            if (array_key_exists($column, $record)) {
                return $record[$column];
            }
        } elseif ($record instanceof \ArrayAccess) {
            if ($record->offsetExists($column)) {
                return $record[$column];
            }
        } elseif (is_object($record)) {
            $properties = get_object_vars($record);
            if (array_key_exists($column, $properties)) {
                return $properties[$column];
            }
        }
        throw new AssociatedFieldNotFound("No existe el atributo físico $column en el registro.");
    }
}
