<?php

declare(strict_types=1);

namespace Ahinest\LaravelPrintKit\Rendering;

use Ahinest\LaravelPrintKit\Contracts\PrintKitModel;
use Ahinest\LaravelPrintKit\Data\ResolvedLabel;
use Ahinest\LaravelPrintKit\Data\ResolvedLabelCollection;
use Illuminate\Database\Eloquent\Model;

/** Prepara el papel una sola vez por llamada y delega exclusivamente renderizado de valores finales. */
final class LabelRenderService
{
    public function __construct(private LabelRenderContextFactory $contexts, private LabelHtmlRenderer $labels, private HtmlDocumentRenderer $documents) {}

    public function render(Model&PrintKitModel $format, ResolvedLabel $label): RenderedLabel
    {
        return $this->labels->render($this->contexts->create($format), $label);
    }

    public function renderDocument(Model&PrintKitModel $format, ResolvedLabel $label): RenderedDocument
    {
        return $this->documents->render($this->render($format, $label));
    }

    public function renderCollection(Model&PrintKitModel $format, ResolvedLabelCollection $labels): RenderedDocument
    {
        $context = $this->contexts->create($format);
        $rendered = [];
        foreach ($labels as $label) {
            $rendered[] = $this->labels->render($context, $label);
        }

        return $this->documents->render($rendered, $context);
    }
}
