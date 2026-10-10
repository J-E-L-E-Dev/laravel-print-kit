<?php

declare(strict_types=1);

namespace Ahinest\LaravelPrintKit\Contracts;

/** Define el contrato mínimo para sustituir modelos sin heredar las clases predeterminadas. */
interface PrintKitModel
{
    public function printKitEntity(): string;

    public function column(string $canonical): string;
}
