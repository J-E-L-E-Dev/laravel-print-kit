<?php

declare(strict_types=1);

namespace Ahinest\LaravelPrintKit\Data\Exceptions;

/** Conserva la clave para diagnóstico sin incluir su valor en el mensaje de log. */
class RecordNotFound extends \RuntimeException
{
    public function __construct(public readonly string $system, public readonly string $driver, public readonly string $keyField, public readonly string|int $keyValue)
    {
        parent::__construct("Registro inexistente para $driver/$system, clave $keyField.");
    }
}
