<?php

declare(strict_types=1);

namespace Ahinest\LaravelPrintKit\Data;

use Ahinest\LaravelPrintKit\Contracts\PrintKitModel;
use Ahinest\LaravelPrintKit\Data\Exceptions\MissingProvidedValue;
use Illuminate\Database\Eloquent\Model;

/** Usa valores finales literalmente, sin catálogo, transformers ni formateo. */
final class ProvidedValuesResolver
{
    public function resolve(Model&PrintKitModel $format, array $values): ResolvedLabel
    {
        $design = FormatInput::design($format);
        $used = [];
        foreach (FormatInput::placeholders($design) as $placeholder) {
            if (! array_key_exists($placeholder, $values)) {
                throw MissingProvidedValue::forPlaceholder($placeholder);
            }
            $used[$placeholder] = $values[$placeholder];
        }

        return new ResolvedLabel($design, $used, new ResolutionContext(null, (string) $format->getAttribute('system'), 'ET', ResolutionMode::ProvidedValues));
    }
}
