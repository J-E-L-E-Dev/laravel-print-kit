<?php

declare(strict_types=1);

namespace Ahinest\LaravelPrintKit\Tests\Fixtures;

use Ahinest\LaravelPrintKit\Contracts\FieldTransformer;
use Ahinest\LaravelPrintKit\Contracts\PrintKitModel;
use Ahinest\LaravelPrintKit\Data\ResolutionContext;
use Illuminate\Database\Eloquent\Model;

/** Transformer de prueba configurable y resoluble mediante el contenedor. */
class PrefixTransformer implements FieldTransformer
{
    public function __construct(private string $prefix = 'config') {}

    public function transform(mixed $value, mixed $record, Model&PrintKitModel $field, ResolutionContext $context): mixed
    {
        return $this->prefix;
    }
}
