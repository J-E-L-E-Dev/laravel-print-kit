<?php

declare(strict_types=1);

namespace Ahinest\LaravelPrintKit\Data;

use Ahinest\LaravelPrintKit\Data\Exceptions\InvalidResolvedValue;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

/** Formatea sin localización ni conversión previa de cadenas decimales a float. */
final class DataTypeFormatter
{
    public function format(mixed $value, DataType $type): string
    {
        if ($type->kind === 'T') {
            throw new InvalidResolvedValue('T requiere un transformer y no pasa por el formatter.');
        }
        if ($type->kind === 'C') {
            ResolvedValue::validate($value);

            return (string) $value;
        }
        if ((! is_int($value) && ! is_float($value) && ! is_string($value))
            || (is_float($value) && ! is_finite($value))
            || (is_string($value) && (strlen($value) > 1024 || ! preg_match('/^[+-]?[0-9]+(?:\.[0-9]+)?$/D', $value)))) {
            throw new InvalidResolvedValue('Se requiere un número decimal finito sin separadores regionales.');
        }
        $decimal = BigDecimal::of(is_float($value) ? (string) $value : $value);
        if ($type->decimalPlaces !== null) {
            return (string) $decimal->toScale($type->decimalPlaces, RoundingMode::HALF_UP);
        }
        if ($type->kind === 'N') {
            return (string) (method_exists($decimal, 'strippedOfTrailingZeros')
                ? $decimal->strippedOfTrailingZeros() : $decimal->stripTrailingZeros());
        }

        return (string) $decimal;
    }
}
