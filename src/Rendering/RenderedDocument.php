<?php

declare(strict_types=1);

namespace Ahinest\LaravelPrintKit\Rendering;

/** Documento inmutable UTF-8 generado exclusivamente a partir de fragmentos seguros del paquete. */
final class RenderedDocument implements \JsonSerializable
{
    private readonly string $content;

    public readonly string $width;

    public readonly string $height;

    public readonly int $count;

    public readonly string $mediaType;

    public readonly string $charset;

    public function __construct(array $labels, ?LabelRenderContext $context = null)
    {
        $document = HtmlDocumentRenderer::layout($labels, $context);
        $this->content = $document['html'];
        $this->width = $document['width'];
        $this->height = $document['height'];
        $this->count = $document['count'];
        $this->mediaType = 'text/html';
        $this->charset = 'utf-8';
    }

    public function html(): string
    {
        return $this->content;
    }

    public function toArray(): array
    {
        return ['html' => $this->content, 'width' => $this->width, 'height' => $this->height, 'count' => $this->count, 'unit' => 'cm', 'mediaType' => $this->mediaType, 'charset' => $this->charset];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
