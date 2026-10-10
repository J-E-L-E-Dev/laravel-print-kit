<?php

declare(strict_types=1);

namespace Ahinest\LaravelPrintKit\Tests;

use Ahinest\LaravelPrintKit\Models\PaperSize;
use Ahinest\LaravelPrintKit\Models\PrintField;
use Ahinest\LaravelPrintKit\Models\PrintFormat;
use Ahinest\LaravelPrintKit\PrintKitServiceProvider;
use Ahinest\LaravelPrintKit\Support\ModelResolver;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;

class InfrastructureTest extends ConfiguredTestCase
{
    public function test_provider_configuration_and_tables(): void
    {
        $this->assertNotNull($this->app->getProvider(PrintKitServiceProvider::class));
        $this->assertSame(PrintField::class, config('print-kit.models.field'));
        $this->assertInstanceOf(PrintField::class, $this->app->make(ModelResolver::class)->make('field'));
        foreach (['print_fields', 'print_paper_sizes', 'print_formats'] as $table) {
            $this->assertTrue(Schema::hasTable($table));
        }
        $this->assertCount(1, PrintKitServiceProvider::pathsToPublish(PrintKitServiceProvider::class, 'print-kit-config'));
        $this->assertCount(1, PrintKitServiceProvider::pathsToPublish(PrintKitServiceProvider::class, 'print-kit-migrations'));
    }

    public function test_field_defaults_and_boolean_cast(): void
    {
        $field = PrintField::create($this->fieldAttributes())->fresh();
        $this->assertSame('mysql', $field->driver);
        $this->assertSame('testing', $field->getConnection()->getName());
        $this->assertSame('INV', $field->system);
        $this->assertSame('ET', $field->category);
        $this->assertSame('C', $field->data_type);
        $this->assertFalse($field->usable_in_detail);
        $field->update(['usable_in_detail' => true]);
        $this->assertTrue($field->fresh()->usable_in_detail);
    }

    public function test_formats_defaults_casts_and_both_relationships(): void
    {
        $paper = $this->paper();
        $format = PrintFormat::create(['code' => 'INVETQ00', 'name' => 'Etiqueta', 'paper_size_id' => $paper->id])->fresh();
        $this->assertSame('5.0000', $paper->fresh()->width);
        $this->assertSame('3.0000', $paper->fresh()->height);
        $this->assertTrue($paper->fresh()->is_active);
        foreach (['copies' => 1, 'version' => 1, 'orientation' => 'V', 'system' => 'INV', 'category' => 'ET', 'preview' => true, 'disabled' => false, 'use_default_printer' => false, 'detail' => ['schema_version' => 1, 'elements' => []], 'parameters' => null] as $key => $expected) {
            $this->assertSame($expected, $format->$key, $key);
        }
        $this->assertSame(['top' => 0, 'right' => 0, 'bottom' => 0, 'left' => 0], $format->margins);
        $this->assertTrue($format->paperSize->is($paper));
        $this->assertTrue($paper->formats->first()->is($format));
        $design = ['schema_version' => 1, 'elements' => [['id' => 'text-1', 'type' => 'text', 'text' => 'Precio', 'x' => '0.0000', 'y' => '0.0000', 'width' => '1.0000', 'height' => '0.5000']]];
        $format->update(['detail' => $design, 'parameters' => ['any' => ['key' => 2]], 'preview' => false, 'disabled' => true, 'use_default_printer' => true]);
        $fresh = $format->fresh();
        $this->assertSame($design, $fresh->detail);
        $this->assertSame(['any' => ['key' => 2]], $fresh->parameters);
        $this->assertFalse($fresh->preview);
        $this->assertTrue($fresh->disabled);
        $this->assertTrue($fresh->use_default_printer);
    }

    public function test_format_code_is_unique_in_database(): void
    {
        $data = ['code' => 'SAME', 'name' => 'Etiqueta', 'paper_size_id' => $this->paper()->id];
        PrintFormat::create($data);
        $this->expectException(QueryException::class);
        PrintFormat::create($data);
    }

    public function test_foreign_key_prevents_orphans(): void
    {
        $paper = $this->paper();
        PrintFormat::create(['code' => 'FK', 'name' => 'Etiqueta', 'paper_size_id' => $paper->id]);
        $this->expectException(QueryException::class);
        $paper->delete();
    }

    #[DataProvider('invalidFormatValues')]
    public function test_format_rejects_invalid_values(string $column, mixed $value): void
    {
        $this->expectException(ValidationException::class);
        new PrintFormat([$column => $value]);
    }

    public static function invalidFormatValues(): array
    {
        return [
            ['copies', 0], ['copies', -1], ['copies', 1.5], ['copies', 'abc'],
            ['version', 0], ['orientation', 'X'], ['system', 'AB'], ['category', 'LONG'],
            ['detail', 'invalid json'], ['margins', ['top' => -1, 'right' => 0, 'bottom' => 0, 'left' => 0]],
            ['margins', ['top' => 0]], ['parameters', 'invalid'], ['preview', 'false'],
        ];
    }

    public function test_bulk_update_validates_before_casting(): void
    {
        $this->expectException(ValidationException::class);
        PrintFormat::query()->update(['copies' => 0]);
    }

    public function test_invalid_dimensions_are_rejected(): void
    {
        $this->expectException(ValidationException::class);
        PaperSize::create(['name' => 'Inválido', 'width' => 0, 'height' => 3]);
    }

    public function test_hidden_fields_do_not_disable_validation_or_persistence(): void
    {
        $field = new PrintField($this->fieldAttributes());
        $field->makeHidden(['driver', 'associated_field']);
        $field->save();
        $this->assertArrayNotHasKey('driver', $field->toArray());
        $this->assertSame('mysql', $field->fresh()->driver);
    }

    public function test_query_from_existing_model_still_validates_bulk_updates(): void
    {
        $format = PrintFormat::create(['code' => 'EXISTING', 'name' => 'Existente', 'paper_size_id' => $this->paper()->id]);
        $this->expectException(ValidationException::class);
        $format->newQuery()->update(['copies' => -1]);
    }

    #[DataProvider('unsupportedWrites')]
    public function test_unsupported_write_shortcuts_fail_explicitly(string $method, array $arguments): void
    {
        $this->expectException(\LogicException::class);
        PrintFormat::query()->$method(...$arguments);
    }

    public static function unsupportedWrites(): array
    {
        return [
            ['increment', ['copies']], ['decrement', ['copies']],
            ['incrementEach', [['copies' => 1]]], ['decrementEach', [['copies' => 1]]],
            ['upsert', [[['code' => 'X']], ['code']]],
        ];
    }

    public function test_associated_field_is_an_identifier(): void
    {
        $this->expectException(ValidationException::class);
        PrintField::create(array_replace($this->fieldAttributes(), ['associated_field' => 'price; DROP TABLE products']));
    }

    public function test_missing_required_attributes_are_rejected(): void
    {
        $this->expectException(ValidationException::class);
        PrintField::create([]);
    }

    public function test_configurable_defaults(): void
    {
        config(['print-kit.defaults.fields.system' => 'POS', 'print-kit.defaults.formats.copies' => 2]);
        $this->assertSame('POS', PrintField::create($this->fieldAttributes())->system);
        $this->assertSame(2, (new PrintFormat)->copies);
    }

    public function test_migrations_reverse_and_ignore_custom_table_settings(): void
    {
        config(['print-kit.tables.fields' => 'external_fields', 'print-kit.columns.fields.placeholder' => 'external_code']);
        $paths = glob(__DIR__.'/../database/migrations/*.php');
        foreach (array_reverse($paths) as $path) {
            (require $path)->down();
        }
        $this->assertFalse(Schema::hasTable('print_fields'));
        $this->assertFalse(Schema::hasTable('print_formats'));
        $this->assertFalse(Schema::hasTable('print_paper_sizes'));
        foreach ($paths as $path) {
            (require $path)->up();
        }
        $this->assertTrue(Schema::hasColumn('print_fields', 'placeholder'));
        $this->assertFalse(Schema::hasTable('external_fields'));
    }

    public function test_database_scalar_defaults_without_models(): void
    {
        DB::table('print_fields')->insert($this->fieldAttributes());
        $field = DB::table('print_fields')->first();
        $this->assertSame('mysql', $field->driver);
        $this->assertSame('INV', $field->system);
        $this->assertSame('ET', $field->category);
        $this->assertEquals(0, $field->usable_in_detail);
    }

    private function fieldAttributes(): array
    {
        return ['placeholder' => 'DESCRIPTION', 'display_name' => 'Descripción', 'associated_field' => 'descripcio'];
    }

    private function paper(): PaperSize
    {
        return PaperSize::create(['name' => 'Etiqueta 5x3', 'width' => '5.00', 'height' => '3.00']);
    }
}
