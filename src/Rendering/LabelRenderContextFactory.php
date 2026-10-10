<?php

declare(strict_types=1);

namespace Ahinest\LaravelPrintKit\Rendering;

use Ahinest\LaravelPrintKit\Contracts\PrintKitModel;
use Ahinest\LaravelPrintKit\Design\InvalidDesign;
use Ahinest\LaravelPrintKit\Design\LabelDesign;
use Ahinest\LaravelPrintKit\Rendering\Exceptions\InvalidRenderContext;
use Ahinest\LaravelPrintKit\Rendering\Exceptions\UnsupportedRenderCategory;
use Ahinest\LaravelPrintKit\Support\InvalidPaperSize;
use Ahinest\LaravelPrintKit\Support\PaperSizeResolver;
use Illuminate\Database\Eloquent\Model;

/** Único adaptador Eloquent del render; reutiliza papel explícito/cargado o realiza una consulta. */
final class LabelRenderContextFactory
{
    public function __construct(private PaperSizeResolver $papers) {}

    public function create(Model&PrintKitModel $format, ?Model $paper = null): LabelRenderContext
    {
        if ($format->printKitEntity() !== 'formats') {
            throw new InvalidRenderContext('Se requiere un modelo de formatos.');
        }
        if ($format->getAttribute('category') !== 'ET') {
            throw new UnsupportedRenderCategory('El renderizado solo admite etiquetas ET.');
        }
        $orientation = $format->getAttribute('orientation');
        $margins = $format->getAttribute('margins');
        $system = $format->getAttribute('system');
        $detail = $format->getAttribute('detail');
        $paperId = $format->getAttribute('paper_size_id');
        if (! is_string($orientation) || ! in_array($orientation, ['V', 'H'], true) || ! is_array($margins)
            || ! is_string($system) || ! is_array($detail) || (! is_int($paperId) && ! is_string($paperId))
            || ! preg_match('/^[1-9][0-9]*$/D', (string) $paperId)) {
            throw new InvalidRenderContext('Faltan atributos válidos de orientación, márgenes, sistema, diseño o papel.');
        }
        try {
            $design = LabelDesign::fromArray($detail);
        } catch (InvalidDesign $exception) {
            throw new InvalidRenderContext($exception->getMessage(), 0, $exception);
        }
        try {
            $paper = $this->papers->resolve($format, $paper);
        } catch (InvalidPaperSize $exception) {
            throw new InvalidRenderContext($exception->getMessage(), 0, $exception);
        }

        return new LabelRenderContext($design, $paper->getAttribute('width'), $paper->getAttribute('height'), $orientation, $margins, $system, 'ET');
    }
}
