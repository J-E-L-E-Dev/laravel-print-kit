<?php

declare(strict_types=1);

namespace Ahinest\LaravelPrintKit\Data;

/** Contexto inmutable de una ejecución; no conserva modelos ni registros mutables. */
final class ResolutionContext
{
    public function __construct(
        public readonly ?string $driver,
        public readonly string $system,
        public readonly string $category,
        public readonly ResolutionMode $mode,
        public readonly string|int|null $key = null,
    ) {}
}
