<?php

declare(strict_types=1);

namespace Ahinest\LaravelPrintKit\Data;

use Ahinest\LaravelPrintKit\Contracts\PrintKitModel;
use Ahinest\LaravelPrintKit\Design\FieldElement;
use Ahinest\LaravelPrintKit\Design\InvalidDesign;
use Ahinest\LaravelPrintKit\Design\LabelDesign;
use Illuminate\Database\Eloquent\Model;

/** Extrae una instantánea del diseño sin consultas ni validación geométrica contra el catálogo. */
final class FormatInput
{
    public static function design(Model&PrintKitModel $format): LabelDesign
    {
        if ($format->printKitEntity() !== 'formats' || $format->getAttribute('category') !== 'ET') {
            throw new InvalidDesign('format', 'La resolución requiere un formato ET.');
        }
        $detail = $format->getAttribute('detail');
        if (! is_array($detail)) {
            throw new InvalidDesign('detail', 'El diseño debe ser un objeto JSON.');
        }

        return LabelDesign::fromArray($detail);
    }

    public static function placeholders(LabelDesign $design): array
    {
        $fields = [];
        foreach ($design->elements() as $element) {
            if ($element instanceof FieldElement) {
                $fields[] = $element->field();
            }
        }

        return array_values(array_unique($fields));
    }
}
