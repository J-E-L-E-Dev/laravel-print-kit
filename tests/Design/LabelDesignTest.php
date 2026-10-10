<?php

declare(strict_types=1);

namespace Ahinest\LaravelPrintKit\Tests\Design;

use Ahinest\LaravelPrintKit\Design\Decimal;
use Ahinest\LaravelPrintKit\Design\DesignElement;
use Ahinest\LaravelPrintKit\Design\ElementStyle;
use Ahinest\LaravelPrintKit\Design\FieldElement;
use Ahinest\LaravelPrintKit\Design\InvalidDesign;
use Ahinest\LaravelPrintKit\Design\LabelDesign;
use Ahinest\LaravelPrintKit\Design\TextElement;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class LabelDesignTest extends TestCase
{
    public function test_creation_round_trip_types_units_and_style(): void
    {
        $data = ['schema_version' => 1, 'elements' => [
            self::element(['type' => 'field', 'field' => 'DESCRIPTION', 'style' => ['font_size' => 10, 'font_weight' => 'bold', 'text_align' => 'center']]),
            self::element(['id' => 'text-2', 'text' => 'Precio Bs.']),
        ]];
        unset($data['elements'][0]['text']);
        $design = LabelDesign::fromArray($data);
        $this->assertInstanceOf(FieldElement::class, $design->elements()[0]);
        $this->assertInstanceOf(TextElement::class, $design->elements()[1]);
        $this->assertSame('DESCRIPTION', $design->elements()[0]->field());
        $this->assertSame('Precio Bs.', $design->elements()[1]->text());
        $this->assertSame('0.2500', (string) $design->elements()[0]->x);
        $this->assertSame(10, $design->elements()[0]->style->fontSize);
        $this->assertSame($design->toArray(), LabelDesign::fromArray($design->toArray())->toArray());
        $this->assertSame($design->toArray(), json_decode(json_encode($design, JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR));
        $this->assertSame(['schema_version' => 1, 'elements' => []], LabelDesign::empty()->toArray());
    }

    #[DataProvider('invalidDesigns')]
    public function test_invalid_structures_are_rejected(array $data, string $path): void
    {
        try {
            LabelDesign::fromArray($data);
            $this->fail('El diseño inválido fue aceptado.');
        } catch (InvalidDesign $exception) {
            $this->assertSame($path, $exception->path);
            $this->assertArrayHasKey($path, $exception->errors());
        }
    }

    public static function invalidDesigns(): array
    {
        $cases = [
            [[], 'detail.schema_version'],
            [['schema_version' => 2, 'elements' => []], 'detail.schema_version'],
            [['schema_version' => '1', 'elements' => []], 'detail.schema_version'],
            [['schema_version' => 1], 'detail.elements'],
            [['schema_version' => 1, 'elements' => 'no'], 'detail.elements'],
            [['schema_version' => 1, 'elements' => [2 => self::element()]], 'detail.elements'],
            [['schema_version' => 1, 'elements' => [null]], 'detail.elements.0'],
            [['schema_version' => 1, 'elements' => [], 'sections' => []], 'detail.sections'],
            [['schema_version' => 1, 'elements' => [self::element(), self::element()]], 'detail.elements.1.id'],
        ];
        foreach ([
            ['type', 'image'], ['id', ''], ['id', 1], ['width', 0], ['height', 0],
            ['width', -1], ['height', '-0.1'], ['x', -1], ['y', '-1'],
            ['x', null], ['y', true], ['width', '1.00001'], ['height', INF],
            ['x', '1e-3'], ['width', '1000000'], ['text', null], ['style', 'bold'],
            ['style', ['font_size' => 0]], ['style', ['font_size' => -10]],
            ['style', ['font_size' => '10']], ['style', ['font_size' => INF]],
            ['style', ['font_size' => null]], ['style', ['font_weight' => 'medium']],
            ['style', ['text_align' => 'justify']], ['style', ['font_weight' => null]],
            ['style', ['color' => 'red']],
        ] as [$property, $value]) {
            $suffix = $property === 'style' && is_array($value) ? '.'.array_key_first($value) : '';
            $cases[] = [['schema_version' => 1, 'elements' => [self::element([$property => $value])]], "detail.elements.0.$property$suffix"];
        }
        $missing = self::element();
        unset($missing['width']);
        $cases[] = [['schema_version' => 1, 'elements' => [$missing]], 'detail.elements.0.width'];

        return $cases;
    }

    public function test_field_requires_a_canonical_placeholder_and_correct_factory(): void
    {
        $data = self::element(['type' => 'field', 'field' => 'DESCRIPTION']);
        unset($data['text']);
        $this->assertInstanceOf(FieldElement::class, FieldElement::fromArray($data));
        $this->expectException(InvalidDesign::class);
        TextElement::fromArray($data);
    }

    public function test_field_cannot_use_an_sql_expression(): void
    {
        $data = self::element(['type' => 'field', 'field' => 'price + tax']);
        unset($data['text']);
        $this->expectException(InvalidDesign::class);
        FieldElement::fromArray($data);
    }

    public function test_manipulation_returns_new_designs_and_preserves_render_order(): void
    {
        $first = DesignElement::fromArray(self::element(['id' => 'first']));
        $second = DesignElement::fromArray(self::element(['id' => 'second']));
        $empty = LabelDesign::empty();
        $design = $empty->withElement($first)->withElement($second);
        $this->assertSame([], $empty->elements());
        $this->assertSame(['first', 'second'], array_column($design->toArray()['elements'], 'id'));
        $reordered = $design->reorder(['second', 'first']);
        $this->assertSame(['second', 'first'], array_column($reordered->toArray()['elements'], 'id'));
        $this->assertSame('first', $design->elements()[0]->id);
        $replacement = DesignElement::fromArray(self::element(['id' => 'first', 'text' => 'Nuevo']));
        $replaced = $design->replaceElement($replacement);
        $this->assertSame('Nuevo', $replaced->elements()[0]->text());
        $this->assertSame('Precio', $design->elements()[0]->text());
        $this->assertSame(['second'], array_column($design->withoutElement('first')->toArray()['elements'], 'id'));
    }

    #[DataProvider('invalidOrders')]
    public function test_invalid_reorder_is_rejected(array $order): void
    {
        $design = LabelDesign::empty()->withElement(DesignElement::fromArray(self::element()));
        $this->expectException(InvalidDesign::class);
        $design->reorder($order);
    }

    public static function invalidOrders(): array
    {
        return [[[]], [['missing']], [['element-1', 'element-1']], [[null]], [[['element-1']]]];
    }

    public function test_duplicate_append_is_rejected(): void
    {
        $element = DesignElement::fromArray(self::element());
        $this->expectException(InvalidDesign::class);
        LabelDesign::empty()->withElement($element)->withElement($element);
    }

    public function test_deleting_unknown_id_is_rejected(): void
    {
        $this->expectException(InvalidDesign::class);
        LabelDesign::empty()->withoutElement('missing');
    }

    public function test_domain_properties_are_immutable(): void
    {
        $element = DesignElement::fromArray(self::element());
        $this->expectException(\Error::class);
        $element->id = 'changed';
    }

    public function test_exact_decimal_arithmetic_and_normalization(): void
    {
        $this->assertFalse(Decimal::sumExceeds(Decimal::from('0.3'), Decimal::from('0.1'), Decimal::from('0.2')));
        $this->assertTrue(Decimal::sumExceeds(Decimal::from('0.2999'), Decimal::from('0.1'), Decimal::from('0.2')));
        $this->assertTrue(Decimal::sumExceeds(Decimal::from('999999.9999'), Decimal::from('999999.9999'), Decimal::from('0.0001')));
        $this->assertSame('0.2500', (string) Decimal::from(0.25));
        $this->assertSame('5.0000', (string) Decimal::from('0005.0'));
        $this->assertSame('"0.2500"', json_encode(Decimal::from('0.25')));
    }

    public function test_typography_is_positive_points_and_has_no_implicit_defaults(): void
    {
        $this->assertSame([], ElementStyle::fromArray([])->toArray());
        $style = ElementStyle::fromArray(['font_size' => 10.5, 'font_weight' => 'normal', 'text_align' => 'right']);
        $this->assertSame(10.5, $style->fontSize);
        $this->assertSame(10.5, $style->toArray()['font_size']);
        $this->assertSame(['font_size' => 0.001], ElementStyle::fromArray(['font_size' => 0.001])->toArray());
    }

    private static function element(array $changes = []): array
    {
        return array_replace(['id' => 'element-1', 'type' => 'text', 'text' => 'Precio', 'x' => '0.25', 'y' => '0.30', 'width' => '4.00', 'height' => '0.50'], $changes);
    }
}
