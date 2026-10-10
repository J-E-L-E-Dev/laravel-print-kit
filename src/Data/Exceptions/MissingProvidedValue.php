<?php

declare(strict_types=1);

namespace Ahinest\LaravelPrintKit\Data\Exceptions;

/** Falta un placeholder utilizado por el diseño entre los valores finales proporcionados. */
class MissingProvidedValue extends \InvalidArgumentException
{
    public ?string $placeholder = null;

    /** Adjunta la clave canónica sin obligar a interpretar el texto del error. */
    public static function forPlaceholder(string $placeholder): self
    {
        $exception = new self("Falta el valor de $placeholder.");
        $exception->placeholder = $placeholder;

        return $exception;
    }
}
