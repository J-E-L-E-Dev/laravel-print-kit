<?php

declare(strict_types=1);

namespace Ahinest\LaravelPrintKit\Design;

use InvalidArgumentException;

/** Identifica la propiedad inválida sin depender de HTTP ni de un formulario. */
class InvalidDesign extends InvalidArgumentException
{
    public function __construct(public readonly string $path, string $reason)
    {
        parent::__construct("$path: $reason");
    }

    public function errors(): array
    {
        return [$this->path => [$this->getMessage()]];
    }
}
