<?php

declare(strict_types=1);

namespace Ahinest\LaravelPrintKit\Design;

/** Comparte las reglas físicas entre validación de diseño y render sin acceder al catálogo. */
final class LabelGeometry
{
    private function __construct(public readonly Decimal $width, public readonly Decimal $height, public readonly string $orientation, public readonly array $margins) {}

    public static function fromPaper(mixed $width, mixed $height, string $orientation, mixed $margins): self
    {
        if (! in_array($orientation, ['V', 'H'], true)) {
            throw new InvalidDesign('orientation', 'Solo se admiten V y H.');
        }
        $width = Decimal::from($width, 'paper.width', true);
        $height = Decimal::from($height, 'paper.height', true);
        if ($orientation === 'H') {
            [$width,$height] = [$height, $width];
        }
        if (! is_array($margins) || array_diff(array_keys($margins), ['top', 'right', 'bottom', 'left']) !== []) {
            throw new InvalidDesign('margins', 'Se requieren top, right, bottom y left.');
        }
        $normalized = [];
        foreach (['top', 'right', 'bottom', 'left'] as $side) {
            $normalized[$side] = Decimal::from($margins[$side] ?? null, "margins.$side");
        }
        $minimum = Decimal::from('0.0001');
        if (Decimal::sumExceeds($width, $normalized['left'], $normalized['right'], $minimum)
            || Decimal::sumExceeds($height, $normalized['top'], $normalized['bottom'], $minimum)) {
            throw new InvalidDesign('margins', 'Los márgenes deben dejar un área imprimible positiva.');
        }

        return new self($width, $height, $orientation, $normalized);
    }

    public function validate(LabelDesign $design): void
    {
        foreach ($design->elements() as $index => $element) {
            if ($element->x->lessThan($this->margins['left']) || Decimal::sumExceeds($this->width, $element->x, $element->width, $this->margins['right'])) {
                throw new InvalidDesign("detail.elements.$index.x", 'El elemento excede el área imprimible horizontal.');
            }
            if ($element->y->lessThan($this->margins['top']) || Decimal::sumExceeds($this->height, $element->y, $element->height, $this->margins['bottom'])) {
                throw new InvalidDesign("detail.elements.$index.y", 'El elemento excede el área imprimible vertical.');
            }
        }
    }
}
