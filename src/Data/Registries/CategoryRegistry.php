<?php

declare(strict_types=1);

namespace Ahinest\LaravelPrintKit\Data\Registries;

use Ahinest\LaravelPrintKit\Data\Exceptions\UnknownCategory;

/** Centraliza las categorías disponibles. */
final class CategoryRegistry extends ConfiguredRegistry
{
    protected function kind(): string
    {
        return 'categories';
    }

    protected function unknown(string $code): never
    {
        throw new UnknownCategory("Código no registrado: $code.");
    }
}
