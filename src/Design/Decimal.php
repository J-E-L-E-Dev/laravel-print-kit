<?php

declare(strict_types=1);

namespace Ahinest\LaravelPrintKit\Design;

use JsonSerializable;
use Stringable;

/** Conserva cuatro decimales y compara sumas exactas sin FLOAT ni extensiones adicionales. */
final class Decimal implements JsonSerializable, Stringable
{
    private function __construct(private readonly string $digits) {}

    public static function from(mixed $value, string $path = 'measure', bool $positive = false): self
    {
        if ((! is_int($value) && ! is_float($value) && ! is_string($value)) ||
            (is_float($value) && ! is_finite($value)) ||
            ! preg_match('/^([0-9]+)(?:\\.([0-9]{1,4}))?$/D', (string) $value, $parts)) {
            throw new InvalidDesign($path, 'Debe ser un decimal no negativo con hasta cuatro decimales.');
        }

        $integer = ltrim($parts[1], '0') ?: '0';
        if (strlen($integer) > 6) {
            throw new InvalidDesign($path, 'La medida máxima es 999999.9999.');
        }

        $digits = str_pad($integer, 6, '0', STR_PAD_LEFT).str_pad($parts[2] ?? '', 4, '0');
        if ($positive && $digits === '0000000000') {
            throw new InvalidDesign($path, 'La medida debe ser mayor que cero.');
        }

        return new self($digits);
    }

    public function lessThan(self $other): bool
    {
        return strcmp($this->digits, $other->digits) < 0;
    }

    /** Suma enteros decimales como cadenas; funciona también sin enteros PHP de 64 bits. */
    public static function sumExceeds(self $limit, self ...$values): bool
    {
        $sum = '0';
        foreach ($values as $value) {
            $length = max(strlen($sum), strlen($value->digits));
            $left = str_pad($sum, $length, '0', STR_PAD_LEFT);
            $right = str_pad($value->digits, $length, '0', STR_PAD_LEFT);
            $carry = 0;
            $sum = '';
            for ($i = $length - 1; $i >= 0; $i--) {
                $digit = (int) $left[$i] + (int) $right[$i] + $carry;
                $sum = ($digit % 10).$sum;
                $carry = intdiv($digit, 10);
            }
            $sum = ($carry ? (string) $carry : '').$sum;
        }
        $sum = ltrim($sum, '0') ?: '0';
        $bound = ltrim($limit->digits, '0') ?: '0';

        return strlen($sum) > strlen($bound) ||
            (strlen($sum) === strlen($bound) && strcmp($sum, $bound) > 0);
    }

    public function __toString(): string
    {
        return (ltrim(substr($this->digits, 0, 6), '0') ?: '0').'.'.substr($this->digits, 6);
    }

    public function jsonSerialize(): string
    {
        return (string) $this;
    }
}
