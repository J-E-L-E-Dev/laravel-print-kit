<?php

declare(strict_types=1);

namespace Ahinest\LaravelPrintKit\Rendering;

use Ahinest\LaravelPrintKit\Data\ResolvedLabel;
use Ahinest\LaravelPrintKit\Design\FieldElement;
use Brick\Math\BigDecimal;

/** Genera un fragmento autónomo desde objetos validados; no depende de Eloquent, Blade ni datos de origen. */
final class LabelHtmlRenderer
{
    public function __construct(private ?QrCodeGenerator $qr = null) {}

    public function render(LabelRenderContext $context, ResolvedLabel $label): RenderedLabel
    {
        return new RenderedLabel($context, $label, $this->qr);
    }

    /** Construye únicamente HTML escapado para el objeto de resultado; no acepta HTML crudo. */
    public static function fragment(LabelRenderContext $context, ResolvedLabel $label, ?QrCodeGenerator $qr = null): string
    {
        $context->assertCompatible($label);
        $reset = 'display:block;box-sizing:border-box;margin:0;padding:0;border:0;min-width:0;min-height:0;max-width:none;max-height:none;';
        $textDefaults = 'font-family:sans-serif;font-style:normal;font-variant:normal;line-height:1.2;text-transform:none;text-decoration:none;text-indent:0;letter-spacing:normal;word-spacing:normal;white-space:normal;overflow-wrap:break-word;word-break:normal;overflow:hidden;';
        $html = '<div class="print-kit-label" style="'.$reset.'position:relative;width:'.$context->width.'cm;height:'.$context->height.'cm;overflow:hidden;">';
        $values = $label->values();
        foreach ($label->design->elements() as $element) {
            if ($element instanceof FieldElement && $element->renderAs() === 'qr') {
                $value = HtmlEscaper::text($values[$element->field()]);
                $style = $reset.'position:absolute;left:'.$element->x.'cm;top:'.$element->y.'cm;width:'.$element->width.'cm;height:'.$element->height.'cm;display:flex;align-items:center;justify-content:center;overflow:hidden;';
                $html .= '<div class="print-kit-element print-kit-field print-kit-qr" data-element-id="'.HtmlEscaper::escape($element->id).'" style="'.HtmlEscaper::escape($style).'">';
                if ($value !== '') {
                    $qr ??= new QrCodeGenerator;
                    $src = $qr->generateDataUri($value, $element->id, $element->field());
                    $side = $element->width->lessThan($element->height) ? $element->width : $element->height;
                    $html .= '<img alt="" src="'.HtmlEscaper::escape($src).'" style="display:block;width:'.$side.'cm;height:'.$side.'cm;object-fit:contain;flex-shrink:0;border:0;margin:0;padding:0;">';
                }
                $html .= '</div>';

                continue;
            }
            $size = (string) BigDecimal::of((string) ($element->style?->fontSize ?? 10));
            if (str_contains($size, '.')) {
                $size = rtrim(rtrim($size, '0'), '.');
            }
            $weight = $element->style?->fontWeight ?? 'normal';
            $align = $element->style?->textAlign ?? 'left';
            $style = $reset.'position:absolute;left:'.$element->x.'cm;top:'.$element->y.'cm;width:'.$element->width.'cm;height:'.$element->height.'cm;'.$textDefaults.'font-size:'.$size.'pt;font-weight:'.$weight.';text-align:'.$align.';';
            $value = $element instanceof FieldElement ? $values[$element->field()] : $element->text();
            $html .= '<div class="print-kit-element print-kit-'.$element->type().'" data-element-id="'.HtmlEscaper::escape($element->id).'" style="'.HtmlEscaper::escape($style).'">'.HtmlEscaper::value($value).'</div>';
        }

        return $html.'</div>';
    }
}
