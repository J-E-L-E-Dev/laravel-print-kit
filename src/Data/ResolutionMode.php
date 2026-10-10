<?php

declare(strict_types=1);

namespace Ahinest\LaravelPrintKit\Data;

/** Distingue valores finales, registro proporcionado y consulta al origen. */
enum ResolutionMode: string
{
    case ProvidedValues = 'provided_values';
    case ProvidedRecord = 'provided_record';
    case Database = 'database';
}
