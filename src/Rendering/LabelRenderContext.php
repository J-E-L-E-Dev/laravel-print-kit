<?php

declare(strict_types=1);

namespace Ahinest\LaravelPrintKit\Rendering;

use Ahinest\LaravelPrintKit\Data\ResolvedLabel;
use Ahinest\LaravelPrintKit\Design\Decimal;
use Ahinest\LaravelPrintKit\Design\InvalidDesign;
use Ahinest\LaravelPrintKit\Design\LabelDesign;
use Ahinest\LaravelPrintKit\Design\LabelGeometry;
use Ahinest\LaravelPrintKit\Rendering\Exceptions\InvalidRenderContext;
use Ahinest\LaravelPrintKit\Rendering\Exceptions\UnsupportedRenderCategory;

/** Instantánea física inmutable; los márgenes validan cajas pero no generan desplazamientos CSS. */
final class LabelRenderContext implements \JsonSerializable
{
    public readonly Decimal $width;

    public readonly Decimal $height;

    public readonly array $margins;

    public function __construct(
        public readonly LabelDesign $design,
        mixed $paperWidth,
        mixed $paperHeight,
        public readonly string $orientation = 'V',
        array $margins = ['top' => 0, 'right' => 0, 'bottom' => 0, 'left' => 0],
        public readonly string $system = 'INV',
        public readonly string $category = 'ET',
    ) {
        if ($category !== 'ET') {
            throw new UnsupportedRenderCategory('El renderizado solo admite etiquetas ET.');
        }
        if (! preg_match('/^[A-Z][A-Z0-9]{2}$/D', $system)) {
            throw new InvalidRenderContext('Se requiere un sistema lógico válido.');
        }
        try {
            $geometry = LabelGeometry::fromPaper($paperWidth, $paperHeight, $orientation, $margins);
            $geometry->validate($design);
        } catch (InvalidDesign $exception) {
            throw new InvalidRenderContext($exception->getMessage(), 0, $exception);
        }
        $this->width = $geometry->width;
        $this->height = $geometry->height;
        $this->margins = array_map(static fn (Decimal $value) => (string) $value, $geometry->margins);
    }

    /** Comprueba compatibilidad semántica sin resolver datos ni consultar el formato. */
    public function assertCompatible(ResolvedLabel $label): void
    {
        if ($label->context->system !== $this->system || $label->context->category !== $this->category
            || $label->design->toArray() !== $this->design->toArray()) {
            throw new InvalidRenderContext('La etiqueta resuelta no corresponde al diseño, sistema o categoría esperados.');
        }
    }

    public function toArray(): array
    {
        return ['width' => (string) $this->width, 'height' => (string) $this->height, 'orientation' => $this->orientation,
            'margins' => $this->margins, 'system' => $this->system, 'category' => $this->category, 'unit' => 'cm'];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
