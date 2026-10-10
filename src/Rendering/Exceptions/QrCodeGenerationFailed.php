<?php

declare(strict_types=1);

namespace Ahinest\LaravelPrintKit\Rendering\Exceptions;

/** Contexto de diseño seguro; no conserva el valor ni la excepción interna del codificador. */
final class QrCodeGenerationFailed extends \RuntimeException
{
    public function __construct(public readonly string $elementId, public readonly string $placeholder)
    {
        parent::__construct('No fue posible generar uno de los códigos QR del formato.');
    }
}
