<?php

declare(strict_types=1);

namespace Ahinest\LaravelPrintKit\Data;

use Ahinest\LaravelPrintKit\Design\LabelDesign;

/** Preparación interna reutilizada por un lote para evitar consultas de catálogo por etiqueta. */
final class ResolutionPlan
{
    public function __construct(public readonly LabelDesign $design, public readonly ResolutionContext $context, public readonly array $fields, public readonly array $transformers) {}
}
