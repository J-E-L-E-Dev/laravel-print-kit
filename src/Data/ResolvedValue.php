<?php

declare(strict_types=1);

namespace Ahinest\LaravelPrintKit\Data;

use Ahinest\LaravelPrintKit\Data\Exceptions\InvalidResolvedValue;

/** Limita los resultados a escalares inmutables y NULL serializables como JSON. */
final class ResolvedValue
{
    public static function validate(mixed $value): void
    {
        if ((! is_scalar($value) && $value !== null) || (is_float($value) && ! is_finite($value))
            || (is_string($value) && ! mb_check_encoding($value, 'UTF-8'))) {
            throw new InvalidResolvedValue('El resultado debe ser escalar finito, texto UTF-8 o NULL.');
        }
    }
}
