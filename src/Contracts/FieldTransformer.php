<?php

declare(strict_types=1);

namespace Ahinest\LaravelPrintKit\Contracts;

use Ahinest\LaravelPrintKit\Data\ResolutionContext;
use Illuminate\Database\Eloquent\Model;

/** Transforma el valor base con acceso al registro completo y al contexto inmutable. */
interface FieldTransformer
{
    public function transform(mixed $value, mixed $record, Model&PrintKitModel $field, ResolutionContext $context): mixed;
}
