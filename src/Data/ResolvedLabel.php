<?php

declare(strict_types=1);

namespace Ahinest\LaravelPrintKit\Data;

use Ahinest\LaravelPrintKit\Data\Exceptions\MissingProvidedValue;
use Ahinest\LaravelPrintKit\Design\FieldElement;
use Ahinest\LaravelPrintKit\Design\LabelDesign;

/** Mantiene el diseño intacto y añade valores únicamente al resultado serializado. */
final class ResolvedLabel implements \JsonSerializable
{
    public function __construct(public readonly LabelDesign $design, private readonly array $values, public readonly ResolutionContext $context)
    {
        foreach ($values as $value) {
            ResolvedValue::validate($value);
        }
        foreach ($design->elements() as $element) {
            if ($element instanceof FieldElement && ! array_key_exists($element->field(), $values)) {
                throw MissingProvidedValue::forPlaceholder($element->field());
            }
        }
    }

    public function values(): array
    {
        return $this->values;
    }

    public function toArray(): array
    {
        $result = $this->design->toArray();
        foreach ($result['elements'] as &$element) {
            if ($element['type'] === 'field') {
                $element['value'] = $this->values[$element['field']];
            }
        }

        return $result;
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
