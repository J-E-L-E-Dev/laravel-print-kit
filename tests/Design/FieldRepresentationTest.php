<?php

declare(strict_types=1);

namespace Ahinest\LaravelPrintKit\Tests\Design;

use Ahinest\LaravelPrintKit\Design\FieldElement;
use Ahinest\LaravelPrintKit\Design\InvalidDesign;
use Ahinest\LaravelPrintKit\Design\LabelDesign;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class FieldRepresentationTest extends TestCase
{
    private function element(): array
    {
        return ['id' => 'product', 'type' => 'field', 'field' => 'PRODUCT', 'x' => 0, 'y' => 0, 'width' => 4, 'height' => 2,
            'style' => ['font_size' => 14, 'font_weight' => 'bold', 'text_align' => 'right']];
    }

    public function test_legacy_and_explicit_text_have_identical_canonical_serialization(): void
    {
        $legacy = FieldElement::fromArray($this->element());
        $explicit = FieldElement::fromArray($this->element() + ['render_as' => 'text']);
        $this->assertSame('text', $legacy->renderAs());
        $this->assertSame($legacy->toArray(), $explicit->toArray());
        $this->assertArrayNotHasKey('render_as', $explicit->toArray());
    }

    public function test_qr_round_trip_and_immutable_switch_preserve_styles(): void
    {
        $text = FieldElement::fromArray($this->element());
        $qr = $text->withRenderAs('qr');
        $this->assertSame('text', $text->renderAs());
        $this->assertSame('qr', $qr->renderAs());
        $this->assertSame($text->style->toArray(), $qr->style->toArray());
        $design = LabelDesign::fromArray(['schema_version' => 1, 'elements' => [$qr->toArray()]]);
        $this->assertSame('qr', LabelDesign::fromArray($design->toArray())->elements()[0]->renderAs());
        $this->assertSame(1, $design->toArray()['schema_version']);
        $this->assertSame($text->toArray(), $qr->withRenderAs('text')->toArray());
    }

    #[DataProvider('invalidRepresentations')]
    public function test_parser_rejects_every_other_representation(mixed $value): void
    {
        try {
            FieldElement::fromArray($this->element() + ['render_as' => $value], 'detail.elements.2');
            $this->fail('Debe rechazar una representación inválida.');
        } catch (InvalidDesign $exception) {
            $this->assertSame('detail.elements.2.render_as', $exception->path);
        }
    }

    public static function invalidRepresentations(): array
    {
        return array_map(fn ($value) => [$value], ['barcode', 'image', 'html', 'svg', 'script', 'QR', 'Text', '', null, false, 1, []]);
    }

    public function test_literal_text_rejects_render_as_even_when_text(): void
    {
        $element = $this->element();
        unset($element['field']);
        $element['type'] = 'text';
        $element['text'] = 'Literal';
        $element['render_as'] = 'text';
        $this->expectException(InvalidDesign::class);
        LabelDesign::fromArray(['schema_version' => 1, 'elements' => [$element]]);
    }
}
