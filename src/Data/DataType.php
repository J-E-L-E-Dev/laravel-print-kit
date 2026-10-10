<?php

declare(strict_types=1);

namespace Ahinest\LaravelPrintKit\Data;

use Ahinest\LaravelPrintKit\Data\Exceptions\InvalidDataType;

/** Representa los códigos compactos; la escala decimal está limitada a 18 posiciones. */
final class DataType
{
    private function __construct(public readonly string $kind, public readonly ?int $decimalPlaces = null) {}

    public static function parse(mixed $code): self
    {
        if (is_string($code) && in_array($code, ['C', 'N', 'D', 'T'], true)) {
            return new self($code);
        }
        if (is_string($code) && preg_match('/^D,(0|[1-9][0-9]?)$/D', $code, $match) && (int) $match[1] <= 18) {
            return new self('D', (int) $match[1]);
        }
        throw new InvalidDataType('Se requiere C, N, D, D,n (0..18) o T.');
    }
}
