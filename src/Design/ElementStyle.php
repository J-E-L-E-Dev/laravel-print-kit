<?php

declare(strict_types=1);

namespace Ahinest\LaravelPrintKit\Design;

use JsonSerializable;

/** Agrupa propiedades tipográficas; font_size es un número positivo en puntos (pt), no centímetros. */
final class ElementStyle implements JsonSerializable
{
    private function __construct(
        public readonly int|float|null $fontSize,
        public readonly ?string $fontWeight,
        public readonly ?string $textAlign,
    ) {}

    public static function fromArray(array $data, string $path = 'style'): self
    {
        foreach (array_keys($data) as $key) {
            if (! in_array($key, ['font_size', 'font_weight', 'text_align'], true)) {
                throw new InvalidDesign("$path.$key", 'Propiedad visual no soportada.');
            }
        }

        $fontSize = $data['font_size'] ?? null;
        if (array_key_exists('font_size', $data) &&
            ((! is_int($fontSize) && ! is_float($fontSize)) || ! is_finite((float) $fontSize) || $fontSize <= 0)) {
            throw new InvalidDesign("$path.font_size", 'Se requiere un número finito mayor que cero, expresado en puntos (pt).');
        }
        $fontWeight = $data['font_weight'] ?? null;
        $textAlign = $data['text_align'] ?? null;

        if (array_key_exists('font_weight', $data) && ! in_array($fontWeight, ['normal', 'bold'], true)) {
            throw new InvalidDesign("$path.font_weight", 'Solo se admiten normal y bold.');
        }
        if (array_key_exists('text_align', $data) && ! in_array($textAlign, ['left', 'center', 'right'], true)) {
            throw new InvalidDesign("$path.text_align", 'Solo se admiten left, center y right.');
        }

        return new self($fontSize, $fontWeight, $textAlign);
    }

    public function toArray(): array
    {
        return array_filter([
            'font_size' => $this->fontSize,
            'font_weight' => $this->fontWeight,
            'text_align' => $this->textAlign,
        ], static fn ($value) => $value !== null);
    }

    public function jsonSerialize(): object
    {
        return (object) $this->toArray();
    }
}
