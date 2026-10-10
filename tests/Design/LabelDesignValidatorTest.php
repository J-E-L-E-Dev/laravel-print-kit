<?php

declare(strict_types=1);

namespace Ahinest\LaravelPrintKit\Tests\Design;

use Ahinest\LaravelPrintKit\Design\InvalidDesign;
use Ahinest\LaravelPrintKit\Design\LabelDesign;
use Ahinest\LaravelPrintKit\Models\PaperSize;
use Ahinest\LaravelPrintKit\Models\PrintField;
use Ahinest\LaravelPrintKit\Models\PrintFormat;
use Ahinest\LaravelPrintKit\Support\ModelResolver;
use Ahinest\LaravelPrintKit\Tests\ConfiguredTestCase;
use Ahinest\LaravelPrintKit\Tests\Fixtures\IndependentFormat;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;

class LabelDesignValidatorTest extends ConfiguredTestCase
{
    public function test_valid_field_and_text_are_saved_and_validated_without_source_queries(): void
    {
        $field = $this->field(['driver' => 'connection_that_does_not_exist']);
        $format = $this->format();
        $design = $this->design([
            $this->element(['type' => 'field', 'field' => $field->placeholder]),
            $this->element(['id' => 'text-2', 'text' => 'Precio Bs.']),
        ]);
        $design->validateFor($format);
        $format->setDesign($design)->save();
        $this->assertSame($design->toArray(), $format->fresh()->design()->toArray());
        $this->assertSame(1, $format->version);
        $this->assertSame(1, $format->detail['schema_version']);
        $this->assertSame($design->toArray(), json_decode(DB::table('print_formats')->value('detail'), true));
    }

    #[DataProvider('unavailableFields')]
    public function test_field_context_and_detail_permission_are_enforced(?array $attributes): void
    {
        if ($attributes !== null) {
            $this->field($attributes);
        }
        $format = $this->format();
        $this->expectException(InvalidDesign::class);
        $this->design([$this->element(['type' => 'field', 'field' => 'DESCRIPTION'])])->validateFor($format);
    }

    public static function unavailableFields(): array
    {
        return [[null], [['system' => 'POS']], [['category' => 'RP']], [['usable_in_detail' => false]], [['placeholder' => 'OTHER', 'associated_field' => 'DESCRIPTION']]];
    }

    #[DataProvider('outsideElements')]
    public function test_paper_and_margin_bounds_are_enforced(array $changes, array $margins): void
    {
        $format = $this->format(['margins' => $margins]);
        $this->expectException(InvalidDesign::class);
        $this->design([$this->element(array_replace(['x' => $margins['left'], 'y' => $margins['top']], $changes))])->validateFor($format);
    }

    public static function outsideElements(): array
    {
        $zero = ['top' => 0, 'right' => 0, 'bottom' => 0, 'left' => 0];
        $margin = ['top' => '0.2', 'right' => '0.2', 'bottom' => '0.2', 'left' => '0.2'];

        return [
            [['x' => '4.80', 'width' => '1.00'], $zero],
            [['y' => '2.80', 'height' => '0.50'], $zero],
            [['x' => '0.1999'], $margin],
            [['y' => '0.1999'], $margin],
            [['x' => '4.3', 'width' => '0.5001'], $margin],
            [['y' => '2.3', 'height' => '0.5001'], $margin],
        ];
    }

    public function test_boundary_inclusive_and_decimal_sums_are_exact(): void
    {
        $paper = PaperSize::create(['name' => 'Pequeño', 'width' => '0.3', 'height' => '0.3']);
        $format = $this->format(['paper_size_id' => $paper->id]);
        $design = $this->design([$this->element(['x' => '0.1', 'y' => 0, 'width' => '0.2', 'height' => '0.3'])]);
        $format->setDesign($design)->save();
        $this->assertSame('0.2000', (string) $format->fresh()->design()->elements()[0]->width);
    }

    public function test_exact_margin_boundary_is_allowed(): void
    {
        $format = $this->format(['margins' => ['top' => '0.2', 'right' => '0.2', 'bottom' => '0.2', 'left' => '0.2']]);
        $design = $this->design([$this->element(['x' => '0.2', 'y' => '0.2', 'width' => '4.6', 'height' => '2.6'])]);
        $format->setDesign($design)->save();
        $this->assertSame($design->toArray(), $format->fresh()->detail);
    }

    public function test_horizontal_orientation_swaps_paper_dimensions(): void
    {
        $format = $this->format(['orientation' => 'H']);
        $design = $this->design([$this->element(['x' => 0, 'y' => '4.5', 'width' => '3', 'height' => '0.5'])]);
        $design->validateFor($format);
        $this->assertSame('H', $format->orientation);
        $format->orientation = 'V';
        $this->expectException(InvalidDesign::class);
        $design->validateFor($format);
    }

    public function test_horizontal_orientation_uses_margins_in_effective_axes(): void
    {
        $format = $this->format(['orientation' => 'H', 'margins' => ['top' => '0.1', 'right' => '0.2', 'bottom' => '0.3', 'left' => '0.4']]);
        $design = $this->design([$this->element(['x' => '0.4', 'y' => '0.1', 'width' => '2.4', 'height' => '4.6'])]);
        $format->setDesign($design)->save();
        $this->assertSame('2.4000', (string) $format->design()->elements()[0]->width);
    }

    public function test_margins_cannot_consume_the_entire_paper_even_with_no_elements(): void
    {
        $format = $this->format();
        $format->margins = ['top' => 0, 'right' => 3, 'bottom' => 0, 'left' => 2];
        $this->expectException(InvalidDesign::class);
        LabelDesign::empty()->validateFor($format);
    }

    public function test_format_revision_is_independent_of_schema_version(): void
    {
        $format = $this->format(['version' => 7]);
        $this->assertSame(7, $format->version);
        $this->assertSame(1, $format->detail['schema_version']);
    }

    public function test_missing_paper_is_rejected(): void
    {
        $format = $this->format();
        $format->paper_size_id = 999999;
        $this->expectException(InvalidDesign::class);
        LabelDesign::empty()->validateFor($format);
    }

    public function test_non_label_format_is_rejected(): void
    {
        $format = $this->format();
        $format->category = 'RP';
        $this->expectException(InvalidDesign::class);
        LabelDesign::empty()->validateFor($format);
    }

    public function test_save_revalidates_after_changing_context(): void
    {
        $format = $this->format();
        $format->setDesign($this->design([$this->element(['width' => 5, 'height' => 3])]))->save();
        $format->orientation = 'H';
        $this->expectException(InvalidDesign::class);
        $format->save();
    }

    public function test_save_rechecks_catalog_changes_and_does_not_trust_loaded_relations(): void
    {
        $field = $this->field();
        $format = $this->format();
        $format->setDesign($this->design([$this->element(['type' => 'field', 'field' => 'DESCRIPTION'])]))->save();
        $format->load('paperSize');
        $field->update(['usable_in_detail' => false]);
        $this->expectException(InvalidDesign::class);
        $format->save();
    }

    #[DataProvider('contextColumns')]
    public function test_bulk_context_updates_cannot_bypass_validation(string $column, mixed $value): void
    {
        $format = $this->format();
        $this->expectException(\LogicException::class);
        PrintFormat::whereKey($format->id)->update([$column => $value]);
    }

    public static function contextColumns(): array
    {
        return [
            ['detail', ['schema_version' => 1, 'elements' => []]],
            ['detail->schema_version', 2], ['margins', []], ['margins->left', 100],
            ['paper_size_id', 999], ['orientation', 'H'], ['system', 'POS'], ['category', 'RP'],
        ];
    }

    public function test_multiple_uses_of_same_placeholder_are_allowed_with_different_ids(): void
    {
        $this->field();
        $format = $this->format();
        $design = $this->design([
            $this->element(['type' => 'field', 'field' => 'DESCRIPTION']),
            $this->element(['id' => 'element-2', 'type' => 'field', 'field' => 'DESCRIPTION']),
        ]);
        $format->setDesign($design)->save();
        $this->assertCount(2, $format->fresh()->design()->elements());
    }

    public function test_validator_accepts_a_completely_replaced_format_model(): void
    {
        $format = $this->format();
        config(['print-kit.models.format' => IndependentFormat::class]);
        $replacement = $this->app->make(ModelResolver::class)->make('format')->newQuery()->findOrFail($format->id);
        $design = $this->design([$this->element()]);
        $design->validateFor($replacement);
        $this->assertInstanceOf(IndependentFormat::class, $replacement);
    }

    public function test_paper_changes_are_rechecked_instead_of_using_a_loaded_relation(): void
    {
        $format = $this->format();
        $format->load('paperSize');
        $design = $this->design([$this->element(['width' => 5])]);
        $format->paperSize->update(['width' => 4]);
        $this->expectException(InvalidDesign::class);
        $design->validateFor($format);
    }

    public function test_validator_inherits_explicit_storage_connection_from_format(): void
    {
        config(['database.connections.tenant' => config('database.connections.testing'), 'print-kit.database.connection' => 'tenant']);
        foreach (glob(__DIR__.'/../../database/migrations/*.php') as $path) {
            (require $path)->up();
        }
        config(['print-kit.database.connection' => null]);
        $paper = PaperSize::on('tenant')->create(['name' => 'Tenant', 'width' => 5, 'height' => 3]);
        PrintField::on('tenant')->create(['placeholder' => 'DESCRIPTION', 'display_name' => 'Descripción', 'associated_field' => 'source', 'driver' => 'nonexistent_source', 'usable_in_detail' => true]);
        $format = PrintFormat::on('tenant')->create(['code' => 'TENANT', 'name' => 'Tenant', 'paper_size_id' => $paper->id]);
        $format->setDesign($this->design([$this->element(['type' => 'field', 'field' => 'DESCRIPTION'])]))->save();
        $this->assertSame(0, DB::connection('testing')->table('print_formats')->count());
        $this->assertSame(1, DB::connection('tenant')->table('print_formats')->count());
        $this->assertSame('DESCRIPTION', $format->fresh()->design()->elements()[0]->field());
    }

    private function format(array $changes = []): PrintFormat
    {
        $paperId = $changes['paper_size_id'] ?? PaperSize::create(['name' => 'Etiqueta', 'width' => 5, 'height' => 3])->id;

        return PrintFormat::create(array_replace(['code' => 'LABEL', 'name' => 'Etiqueta', 'paper_size_id' => $paperId], $changes));
    }

    private function field(array $changes = []): PrintField
    {
        return PrintField::create(array_replace(['placeholder' => 'DESCRIPTION', 'display_name' => 'Descripción', 'associated_field' => 'descripcio', 'usable_in_detail' => true], $changes));
    }

    private function design(array $elements): LabelDesign
    {
        return LabelDesign::fromArray(['schema_version' => 1, 'elements' => $elements]);
    }

    private function element(array $changes = []): array
    {
        $data = array_replace(['id' => 'element-1', 'type' => 'text', 'text' => 'Precio', 'x' => 0, 'y' => 0, 'width' => 1, 'height' => '0.5'], $changes);
        if ($data['type'] === 'field') {
            unset($data['text']);
        }

        return $data;
    }
}
