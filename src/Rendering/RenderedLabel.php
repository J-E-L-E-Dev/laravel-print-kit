<?php

declare(strict_types=1);

namespace Ahinest\LaravelPrintKit\Rendering;

use Ahinest\LaravelPrintKit\Data\ResolvedLabel;

/** Fragmento y metadatos físicos inmutables; solo se construye desde datos resueltos, nunca desde HTML arbitrario. */
final class RenderedLabel implements \JsonSerializable
{
    private readonly string $content;

    public readonly string $width;

    public readonly string $height;

    public readonly string $orientation;

    public readonly array $margins;

    public function __construct(LabelRenderContext $context, ResolvedLabel $label, ?QrCodeGenerator $qr = null)
    {
        $this->content = LabelHtmlRenderer::fragment($context, $label, $qr);
        $this->width = (string) $context->width;
        $this->height = (string) $context->height;
        $this->orientation = $context->orientation;
        $this->margins = $context->margins;
    }

    public function html(): string
    {
        return $this->content;
    }

    public function toArray(): array
    {
        return ['html' => $this->content, 'width' => $this->width, 'height' => $this->height, 'orientation' => $this->orientation, 'margins' => $this->margins, 'unit' => 'cm'];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
