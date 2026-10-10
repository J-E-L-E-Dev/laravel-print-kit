<?php

declare(strict_types=1);

namespace Ahinest\LaravelPrintKit\Design;

/** Referencia el placeholder canónico de PrintField, nunca associated_field. */
final class FieldElement extends DesignElement
{
    protected function __construct(
        string $id, Decimal $x, Decimal $y, Decimal $width, Decimal $height,
        ?ElementStyle $style, string $content, private readonly string $representation = 'text',
    ) {
        parent::__construct($id, $x, $y, $width, $height, $style, $content);
    }

    public function renderAs(): string
    {
        return $this->representation;
    }

    public function withRenderAs(string $representation): self
    {
        return self::fromArray(array_replace($this->toArray(), ['render_as' => $representation]));
    }

    public function type(): string
    {
        return 'field';
    }

    public function field(): string
    {
        return $this->content;
    }
}
