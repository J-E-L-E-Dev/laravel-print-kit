<?php

declare(strict_types=1);

namespace Ahinest\LaravelPrintKit\Data\Registries;

use Ahinest\LaravelPrintKit\Data\Exceptions\UnknownDriver;

/** Centraliza las conexiones permitidas. */
final class DriverRegistry extends ConfiguredRegistry
{
    protected function kind(): string
    {
        return 'drivers';
    }

    protected function unknown(string $code): never
    {
        throw new UnknownDriver("Código no registrado: $code.");
    }
}
