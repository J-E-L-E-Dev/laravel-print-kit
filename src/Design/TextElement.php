<?php

declare(strict_types=1);

namespace Ahinest\LaravelPrintKit\Design;

/** Conserva texto literal; su interpretación y escape corresponden al renderer futuro. */
final class TextElement extends DesignElement
{
    public function type(): string
    {
        return 'text';
    }

    public function text(): string
    {
        return $this->content;
    }
}
