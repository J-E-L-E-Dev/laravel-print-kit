<?php

declare(strict_types=1);

namespace Ahinest\LaravelPrintKit\Tests\Rendering;

use Ahinest\LaravelPrintKit\Rendering\Exceptions\QrCodeGenerationFailed;
use Ahinest\LaravelPrintKit\Rendering\LabelHtmlRenderer;
use Ahinest\LaravelPrintKit\Rendering\QrCodeGenerator;
use PHPUnit\Framework\Attributes\DataProvider;

class QrRenderingTest extends RenderingTestCase
{
    #[DataProvider('values')]
    public function test_final_values_reach_generator_exactly(mixed $value, string $expected): void
    {
        $generator = new class extends QrCodeGenerator
        {
            public array $seen = [];

            public function generateDataUri(string $value, string $elementId = '', string $placeholder = ''): string
            {
                $this->seen[] = [$value, $elementId, $placeholder];

                return 'data:image/svg+xml;base64,'.base64_encode('<svg xmlns="http://www.w3.org/2000/svg"/>');
            }
        };
        $design = $this->design(['render_as' => 'qr']);
        $html = (new LabelHtmlRenderer($generator))->render($this->context($design), $this->label($value, $design))->html();
        $this->assertSame([[$expected, 'value', 'VALUE']], $generator->seen);
        $this->assertSame(1, $this->dom($html)->query('//img')->length);
    }

    public static function values(): array
    {
        return [['001-012', '001-012'], ['001234', '001234'], [' 001234 ', ' 001234 '], ['0', '0'],
            ['Español ñ 日本語 €', 'Español ñ 日本語 €'], [false, '0'], [true, '1'], [0, '0'], [125.5, '125.5']];
    }

    #[DataProvider('emptyValues')]
    public function test_empty_values_do_not_invoke_generator(mixed $value): void
    {
        $generator = new class extends QrCodeGenerator
        {
            public function generateDataUri(string $value, string $elementId = '', string $placeholder = ''): string
            {
                throw new \LogicException('No debe generar QR vacío.');
            }
        };
        $design = $this->design(['render_as' => 'qr']);
        $html = (new LabelHtmlRenderer($generator))->render($this->context($design), $this->label($value, $design))->html();
        $dom = $this->dom($html);
        $this->assertSame(0, $dom->query('//img')->length);
        $this->assertSame('', $dom->query('//div[@data-element-id="value"]')->item(0)->textContent);
    }

    public static function emptyValues(): array
    {
        return [[null], ['']];
    }

    public function test_real_svg_is_safe_square_and_has_quiet_zone(): void
    {
        $uri = (new QrCodeGenerator)->generateDataUri('<script>alert(1)</script>');
        $this->assertStringStartsWith('data:image/svg+xml;base64,', $uri);
        $svg = base64_decode(substr($uri, strlen('data:image/svg+xml;base64,')), true);
        $document = new \DOMDocument;
        $this->assertTrue($document->loadXML($svg, LIBXML_NONET));
        $this->assertSame('svg', $document->documentElement->localName);
        $this->assertSame($document->documentElement->getAttribute('width'), $document->documentElement->getAttribute('height'));
        $xpath = new \DOMXPath($document);
        $this->assertGreaterThan(0, $xpath->query('//*[local-name()="path"]')->length);
        $this->assertSame(0, $xpath->query('//*[local-name()="script" or local-name()="text" or local-name()="image"]')->length);
        $this->assertStringNotContainsString('alert(1)', $svg);
        $this->assertMatchesRegularExpression('/translate\(\s*4(?:\.0+)?[, ]+\s*4(?:\.0+)?\)/', $svg);
        $this->assertStringContainsString('#fff', strtolower($svg));
    }

    public function test_rectangular_box_centers_square_and_ignores_typography(): void
    {
        $design = $this->design(['render_as' => 'qr', 'width' => 4, 'height' => 2,
            'style' => ['font_size' => 40, 'font_weight' => 'bold', 'text_align' => 'right']]);
        $html = (new LabelHtmlRenderer)->render($this->context($design), $this->label('<script>alert(1)</script>', $design))->html();
        $dom = $this->dom($html);
        $box = $this->styles($dom->query('//div[@data-element-id="value"]')->item(0));
        $img = $dom->query('//img')->item(0);
        $this->assertSame('4.0000cm', $box['width']);
        $this->assertSame('2.0000cm', $box['height']);
        $this->assertSame('center', $box['align-items']);
        $this->assertSame('center', $box['justify-content']);
        $this->assertArrayNotHasKey('font-size', $box);
        $this->assertArrayNotHasKey('font-weight', $box);
        $this->assertArrayNotHasKey('text-align', $box);
        $this->assertSame('2.0000cm', $this->styles($img)['width']);
        $this->assertSame('2.0000cm', $this->styles($img)['height']);
        $this->assertSame('contain', $this->styles($img)['object-fit']);
        $this->assertSame('', $img->getAttribute('alt'));
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringNotContainsString('alert(1)', $html);
    }

    public function test_old_text_output_is_identical_to_explicit_text(): void
    {
        $renderer = new LabelHtmlRenderer;
        $legacy = $this->design();
        $explicit = $this->design(['render_as' => 'text']);
        $this->assertSame($renderer->render($this->context($legacy), $this->label('<b>001</b>', $legacy))->html(),
            $renderer->render($this->context($explicit), $this->label('<b>001</b>', $explicit))->html());
    }

    public function test_encoding_error_has_context_but_never_content_or_library_exception(): void
    {
        try {
            (new QrCodeGenerator)->generateDataUri(str_repeat('private-value-', 1000), 'product-qr', 'PRODUCT');
            $this->fail('La capacidad de QR es finita.');
        } catch (QrCodeGenerationFailed $exception) {
            $this->assertSame('product-qr', $exception->elementId);
            $this->assertSame('PRODUCT', $exception->placeholder);
            $this->assertNull($exception->getPrevious());
            $this->assertStringNotContainsString('private-value', $exception->getMessage());
        }
    }
}
