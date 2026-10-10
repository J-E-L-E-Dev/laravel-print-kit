<?php

declare(strict_types=1);

namespace Ahinest\LaravelPrintKit\Data\Registries;

use Ahinest\LaravelPrintKit\Data\Exceptions\InvalidRegistryConfiguration;

/** Centraliza códigos, identificadores físicos y metadatos de configuración. */
final class RegistryValidation
{
    public static function identifier(mixed $value): bool
    {
        return is_string($value) && strlen($value) <= 128 && preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/D', $value) === 1;
    }

    public static function code(string $kind, mixed $value): bool
    {
        return is_string($value) && preg_match(match ($kind) {
            'drivers' => '/^[a-zA-Z0-9_-]{1,100}$/D',
            'systems' => '/^[A-Z][A-Z0-9]{2}$/D',
            'categories' => '/^[A-Z][A-Z0-9]$/D',
        }, $value) === 1;
    }

    public static function entry(string $kind, mixed $code, mixed $data): void
    {
        if (! self::code($kind, $code) || ! is_array($data) || ! is_string($data['label'] ?? null)
            || trim($data['label']) === '' || ! is_string($data['description'] ?? null)) {
            throw new InvalidRegistryConfiguration("Configuración inválida en print-kit.$kind.");
        }
    }
}
