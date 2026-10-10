<?php

declare(strict_types=1);

namespace Ahinest\LaravelPrintKit\Tests\Rendering;

use Ahinest\LaravelPrintKit\Design\LabelDesign;
use Ahinest\LaravelPrintKit\Rendering\Exceptions\InvalidRenderContext;
use Ahinest\LaravelPrintKit\Rendering\Exceptions\UnsupportedRenderCategory;
use Ahinest\LaravelPrintKit\Rendering\HtmlEscaper;
use Ahinest\LaravelPrintKit\Rendering\LabelHtmlRenderer;
use Ahinest\LaravelPrintKit\Rendering\LabelRenderContext;
use PHPUnit\Framework\Attributes\DataProvider;

class LabelHtmlRendererTest extends RenderingTestCase
{
    #[DataProvider('orientations')]
    public function test_physical_geometry_and_no_double_margins(string $orientation, string $width, string $height): void
    {
        $label = $this->label();
        $before = $label->toArray();
        $context = $this->context(orientation: $orientation);
        $rendered = (new LabelHtmlRenderer)->render($context, $label);
        $this->assertSame($width, $rendered->width);
        $this->assertSame($height, $rendered->height);
        $this->assertSame($orientation, $rendered->orientation);
        $dom = $this->dom($rendered->html());
        $styles = $this->styles($dom->query('//div[@class="print-kit-label"]')->item(0));
        $this->assertSame($width.'cm', $styles['width']);
        $this->assertSame($height.'cm', $styles['height']);
        $this->assertSame('relative', $styles['position']);
        $this->assertSame('0', $styles['padding']);
        $this->assertSame('0', $styles['margin']);
        $box = $this->styles($dom->query('//div[@data-element-id="value"]')->item(0));
        $this->assertSame('0.2000cm', $box['left']);
        $this->assertSame('0.3000cm', $box['top']);
        $this->assertSame('2.0000cm', $box['width']);
        $this->assertSame('0.5000cm', $box['height']);
        $this->assertSame('absolute', $box['position']);
        $this->assertSame('border-box', $box['box-sizing']);
        $this->assertSame('0.2000', $rendered->margins['left']);
        $this->assertSame($before, $label->toArray());
        $this->assertStringNotContainsString('<html', $rendered->html());
        $this->assertStringNotContainsString('0.4000cm', $rendered->html());
    }

    public static function orientations(): array
    {
        return [['V', '5.0000', '3.0000'], ['H', '3.0000', '5.0000']];
    }

    public function test_render_defaults_and_overflow_do_not_mutate_design(): void
    {
        $label = $this->label();
        $rendered = (new LabelHtmlRenderer)->render($this->context(), $label);
        $node = $this->dom($rendered->html())->query('//div[@data-element-id="value"]')->item(0);
        $styles = $this->styles($node);
        foreach (['font-size' => '10pt', 'font-weight' => 'normal', 'text-align' => 'left', 'font-family' => 'sans-serif', 'line-height' => '1.2',
            'overflow' => 'hidden', 'white-space' => 'normal', 'overflow-wrap' => 'break-word', 'word-break' => 'normal', 'padding' => '0', 'border' => '0'] as $key => $value) {
            $this->assertSame($value, $styles[$key]);
        }
        $this->assertArrayNotHasKey('style', $label->design->toArray()['elements'][0]);
        $this->assertArrayNotHasKey('vertical-align', $styles);
        $this->assertArrayNotHasKey('background', $styles);
        $this->assertArrayNotHasKey('color', $styles);
    }

    #[DataProvider('styleCases')]
    public function test_known_styles(array $style, string $size, string $weight, string $align): void
    {
        $design = $this->design(['style' => $style]);
        $html = (new LabelHtmlRenderer)->render($this->context($design), $this->label(design: $design))->html();
        $styles = $this->styles($this->dom($html)->query('//div[@data-element-id="value"]')->item(0));
        $this->assertSame($size.'pt', $styles['font-size']);
        $this->assertSame($weight, $styles['font-weight']);
        $this->assertSame($align, $styles['text-align']);
    }

    public static function styleCases(): array
    {
        return [[['font_size' => 12.5, 'font_weight' => 'bold', 'text_align' => 'center'], '12.5', 'bold', 'center'], [['text_align' => 'right'], '10', 'normal', 'right'], [['font_size' => 0.000001], '0.000001', 'normal', 'left'], [[], '10', 'normal', 'left']];
    }

    #[DataProvider('values')]
    public function test_values_remain_literal_and_safe(mixed $value, string $expected): void
    {
        $html = (new LabelHtmlRenderer)->render($this->context(), $this->label($value))->html();
        $dom = $this->dom($html);
        $node = $dom->query('//div[@data-element-id="value"]')->item(0);
        $this->assertSame($expected, $node->textContent);
        $this->assertSame(0, $node->childNodes->length === 0 ? 0 : $dom->query('./*', $node)->length);
        $this->assertSame(0, $dom->query('//script|//svg|//img|//b|//@onclick|//@onerror')->length);
    }

    public static function values(): array
    {
        return [[null, ''], ['', ''], [true, '1'], [false, '0'], [0, '0'], [125.5, '125.5'], ['125.5000', '125.5000'],
            ['<script>alert("x")</script>', '<script>alert("x")</script>'], ['<b>texto</b>', '<b>texto</b>'],
            ['<svg onload="alert(1)">x</svg>', '<svg onload="alert(1)">x</svg>'],
            ['&lt;script&gt;', '&lt;script&gt;'], [' á ñ € ¥ 中文 😀 ', ' á ñ € ¥ 中文 😀 '], [" a  b\nc ", " a  b\nc "]];
    }

    public function test_literal_text_attribute_escaping_and_order(): void
    {
        $data = $this->design()->toArray();
        $data['elements'][0]['id'] = 'x" onclick="alert(1)"><script>';
        $data['elements'][1]['text'] = '<b>literal & "ñ"</b>';
        $design = LabelDesign::fromArray($data);
        $html = (new LabelHtmlRenderer)->render($this->context($design), $this->label(design: $design))->html();
        $this->assertStringContainsString('&lt;b&gt;literal &amp; &quot;ñ&quot;&lt;/b&gt;', $html);
        $dom = $this->dom($html);
        $nodes = $dom->query('//div[@data-element-id]');
        $this->assertSame($data['elements'][0]['id'], $nodes->item(0)->getAttribute('data-element-id'));
        $this->assertSame('caption', $nodes->item(1)->getAttribute('data-element-id'));
        $this->assertSame($data['elements'][1]['text'], $nodes->item(1)->textContent);
        $this->assertSame(0, $dom->query('//script|//b|//@onclick')->length);
        $this->assertSame('&apos;', HtmlEscaper::escape("'"));
    }

    public function test_immutable_serializable_results(): void
    {
        $context = $this->context();
        $rendered = (new LabelHtmlRenderer)->render($context, $this->label());
        $this->assertSame($rendered->toArray(), json_decode(json_encode($rendered), true));
        $this->assertSame($context->toArray(), json_decode(json_encode($context), true));
        $copy = $rendered->margins;
        $copy['left'] = '999';
        $this->assertSame('0.2000', $rendered->margins['left']);
        $this->expectException(\Error::class);
        $rendered->width = '6';
    }

    #[DataProvider('incompatibilities')]
    public function test_rejects_incompatible_resolved_label(string $case): void
    {
        $label = match ($case) {
            'system' => $this->label(system: 'POS'), 'category' => $this->label(category: 'RP'),
            'design' => $this->label(design: $this->design(['x' => '0.25'])),
        };
        $this->expectException(InvalidRenderContext::class);
        (new LabelHtmlRenderer)->render($this->context(), $label);
    }

    public static function incompatibilities(): array
    {
        return [['system'], ['category'], ['design']];
    }

    #[DataProvider('invalidContexts')]
    public function test_invalid_context(array $changes): void
    {
        $args = array_replace(['design' => $this->design(), 'paperWidth' => '5', 'paperHeight' => '3', 'orientation' => 'V', 'margins' => ['top' => 0, 'right' => 0, 'bottom' => 0, 'left' => 0]], $changes);
        $this->expectException(InvalidRenderContext::class);
        new LabelRenderContext(...$args);
    }

    public static function invalidContexts(): array
    {
        return [[['paperWidth' => 0]], [['paperHeight' => '3; color:red']], [['paperWidth' => '0.1']], [['paperHeight' => '1.1']],
            [['orientation' => 'landscape']], [['margins' => ['top' => 0, 'right' => 0, 'bottom' => 0, 'left' => '0.3']]],
            [['margins' => ['top' => 0, 'right' => 0, 'bottom' => 0, 'left' => '5']]], [['margins' => []]],
            [['margins' => ['top' => 0, 'right' => 0, 'bottom' => 0, 'left' => -1]]], [['system' => '*']]];
    }

    public function test_only_et_is_supported(): void
    {
        $this->expectException(UnsupportedRenderCategory::class);
        new LabelRenderContext($this->design(), 5, 3, category: 'RP');
    }

    public function test_empty_design_renders_empty_label(): void
    {
        $design = LabelDesign::empty();
        $label = $this->label(design: $design);
        $html = (new LabelHtmlRenderer)->render($this->context($design), $label)->html();
        $this->assertSame(1, $this->dom($html)->query('//div')->length);
    }
}
