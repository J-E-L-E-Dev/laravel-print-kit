<?php

declare(strict_types=1);

namespace Ahinest\LaravelPrintKit\Rendering;

use Ahinest\LaravelPrintKit\Rendering\Exceptions\IncompatibleRenderedLabels;

/** Compone fragmentos del paquete en páginas físicas uniformes sin acceder a modelos. */
final class HtmlDocumentRenderer
{
    public function render(RenderedLabel|array $labels, ?LabelRenderContext $context = null): RenderedDocument
    {
        return new RenderedDocument($labels instanceof RenderedLabel ? [$labels] : $labels, $context);
    }

    /** Valida una lista ordenada y genera el documento; un contexto permite representar la lista vacía. */
    public static function layout(array $labels, ?LabelRenderContext $context = null): array
    {
        if (! array_is_list($labels)) {
            throw new IncompatibleRenderedLabels('Se requiere una lista ordenada de RenderedLabel.');
        }
        $width = $context === null ? null : (string) $context->width;
        $height = $context === null ? null : (string) $context->height;
        $body = '';
        foreach ($labels as $label) {
            if (! $label instanceof RenderedLabel) {
                throw new IncompatibleRenderedLabels('Solo se admiten fragmentos RenderedLabel, no HTML crudo.');
            }
            $width ??= $label->width;
            $height ??= $label->height;
            if ($width !== $label->width || $height !== $label->height) {
                throw new IncompatibleRenderedLabels('Las etiquetas deben compartir dimensiones físicas efectivas.');
            }
            $body .= $label->html();
        }
        if ($width === null || $height === null) {
            throw new IncompatibleRenderedLabels('Una lista vacía necesita contexto físico explícito.');
        }
        $css = '@page{size:'.$width.'cm '.$height.'cm;margin:0;}html,body{margin:0;padding:0;}'
            .'.print-kit-label,.print-kit-label *{box-sizing:border-box;}'
            .'@media print{.print-kit-label{break-inside:avoid;page-break-inside:avoid;break-after:page;page-break-after:always;}'
            .'.print-kit-label:last-child{break-after:auto;page-break-after:auto;}}';
        $html = '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Etiquetas</title><style>'.$css.'</style></head><body>'.$body.'</body></html>';

        return ['html' => $html, 'width' => $width, 'height' => $height, 'count' => count($labels)];
    }
}
