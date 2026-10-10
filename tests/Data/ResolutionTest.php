<?php

declare(strict_types=1);

namespace Ahinest\LaravelPrintKit\Tests\Data;

use Ahinest\LaravelPrintKit\Contracts\FieldTransformer;
use Ahinest\LaravelPrintKit\Contracts\PrintKitModel;
use Ahinest\LaravelPrintKit\Data\Exceptions\AssociatedFieldNotFound;
use Ahinest\LaravelPrintKit\Data\Exceptions\DuplicatePrintFieldDefinition;
use Ahinest\LaravelPrintKit\Data\Exceptions\InvalidRegistryConfiguration;
use Ahinest\LaravelPrintKit\Data\Exceptions\InvalidResolvedValue;
use Ahinest\LaravelPrintKit\Data\Exceptions\MissingProvidedValue;
use Ahinest\LaravelPrintKit\Data\Exceptions\PrintFieldNotFound;
use Ahinest\LaravelPrintKit\Data\Exceptions\RecordNotFound;
use Ahinest\LaravelPrintKit\Data\Exceptions\TransformerNotFound;
use Ahinest\LaravelPrintKit\Data\Exceptions\UnknownDriver;
use Ahinest\LaravelPrintKit\Data\Exceptions\UnknownSystem;
use Ahinest\LaravelPrintKit\Data\FieldTransformerRegistry;
use Ahinest\LaravelPrintKit\Data\LabelResolver;
use Ahinest\LaravelPrintKit\Data\PrintFieldResolver;
use Ahinest\LaravelPrintKit\Data\ResolutionContext;
use Ahinest\LaravelPrintKit\Data\ResolutionMode;
use Ahinest\LaravelPrintKit\Data\ResolvedLabelCollection;
use Ahinest\LaravelPrintKit\Design\InvalidDesign;
use Ahinest\LaravelPrintKit\Design\LabelDesign;
use Ahinest\LaravelPrintKit\Models\PaperSize;
use Ahinest\LaravelPrintKit\Models\PrintField;
use Ahinest\LaravelPrintKit\Models\PrintFormat;
use Ahinest\LaravelPrintKit\Tests\ConfiguredTestCase;
use Ahinest\LaravelPrintKit\Tests\Fixtures\IndependentField;
use Ahinest\LaravelPrintKit\Tests\Fixtures\IndependentFormat;
use Ahinest\LaravelPrintKit\Tests\Fixtures\ScopedField;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;

class ResolutionTest extends ConfiguredTestCase
{
    public function test_independent_catalog_model_is_supported(): void
    {
        $this->fields();
        config(['print-kit.models.field' => IndependentField::class]);
        $field = app(PrintFieldResolver::class)->resolve('mysql', 'INV', 'ET', 'DESCRIPTION');
        $this->assertInstanceOf(IndependentField::class, $field);
        $this->assertSame('125.50', app(LabelResolver::class)->resolveWithRecord($this->format(), 'mysql', ['descripcio' => 'X', 'precio1' => '125.5'])->values()['PRICE']);
    }

    public function test_source_transformer_gets_all_columns_and_database_context(): void
    {
        $this->fields();
        $this->setupSource();
        $transformer = new class implements FieldTransformer
        {
            public ?ResolutionContext $context = null;

            public function transform(mixed $value, mixed $record, Model&PrintKitModel $field, ResolutionContext $context): mixed
            {
                $this->context = $context;

                return $value.' / '.$record->marca;
            }
        };
        app(FieldTransformerRegistry::class)->register('INV', '*', 'DESCRIPTION', $transformer, 'mysql');
        $label = app(LabelResolver::class)->resolveByKey($this->format(), 'mysql', 'P001');
        $this->assertSame('Uno / A', $label->values()['DESCRIPTION']);
        $this->assertSame(ResolutionMode::Database, $transformer->context->mode);
        $this->assertSame('P001', $transformer->context->key);
    }

    public function test_batch_chunks_large_key_lists_without_catalog_n_plus_one(): void
    {
        $this->fields();
        $this->setupSource();
        $rows = [];
        $keys = [];
        for ($i = 0; $i < 501; $i++) {
            $key = 'K'.$i;
            $keys[] = $key;
            $rows[] = ['producto' => $key, 'descripcio' => $key, 'precio1' => '0', 'marca' => 'x'];
        }
        foreach (array_chunk($rows, 100) as $chunk) {
            DB::connection('mysql')->table('I00001')->insert($chunk);
        }
        DB::connection('mysql')->flushQueryLog();
        $this->watch();
        $labels = app(LabelResolver::class)->resolveByKeys($this->format(), 'mysql', $keys);
        $this->assertCount(501, $labels);
        $this->assertCount(1, DB::getQueryLog());
        $this->assertCount(2, DB::connection('mysql')->getQueryLog());
        $this->assertCount(500, DB::connection('mysql')->getQueryLog()[0]['bindings']);
    }

    public function test_alternate_driver_system_table_key_and_zero_key(): void
    {
        $this->field(['driver' => 'mssql']);
        $this->field(['driver' => 'mssql', 'placeholder' => 'PRICE', 'associated_field' => 'precio1', 'data_type' => 'D,2']);
        config(['database.connections.mssql' => config('database.connections.testing'), 'print-kit.systems.INV.table' => 'products', 'print-kit.systems.INV.key' => 'sku']);
        Schema::connection('mssql')->create('products', function ($table) {
            $table->integer('sku');
            $table->string('descripcio');
            $table->string('precio1');
        });
        DB::connection('mssql')->table('products')->insert(['sku' => 0, 'descripcio' => 'Zero', 'precio1' => '0']);
        $label = app(LabelResolver::class)->resolveByKey($this->format(), 'mssql', 0);
        $this->assertSame(['DESCRIPTION' => 'Zero', 'PRICE' => '0.00'], $label->values());
        $this->assertSame(0, $label->context->key);
    }

    public function test_non_unique_source_key_is_explicit_error(): void
    {
        $this->fields();
        config(['database.connections.mysql' => config('database.connections.testing')]);
        Schema::connection('mysql')->create('I00001', fn ($table) => $table->string('producto'));
        DB::connection('mysql')->table('I00001')->insert([['producto' => 'X'], ['producto' => 'X']]);
        $this->expectException(InvalidResolvedValue::class);
        app(LabelResolver::class)->resolveByKey($this->format(), 'mysql', 'X');
    }

    public function test_text_only_provided_record_avoids_catalog(): void
    {
        $format = new PrintFormat;
        $this->watch();
        $label = app(LabelResolver::class)->resolveWithRecord($format, 'mysql', []);
        $this->assertSame([], $label->values());
        $this->assertCount(0, DB::getQueryLog());
    }

    public function test_field_resolver_rejects_unregistered_driver_without_queries(): void
    {
        $this->watch();
        try {
            app(PrintFieldResolver::class)->fieldsFor('unregistered', 'INV', 'ET');
            $this->fail();
        } catch (UnknownDriver) {
            $this->assertCount(0, DB::getQueryLog());
        }
    }

    public function test_resolution_rejects_corrupt_physical_identifier_from_raw_storage(): void
    {
        $this->fields();
        DB::table('print_fields')->where('placeholder', 'DESCRIPTION')->update(['associated_field' => 'UPPER(name)']);
        $this->expectException(InvalidRegistryConfiguration::class);
        app(LabelResolver::class)->resolveWithRecord($this->format(), 'mysql', []);
    }

    public function test_t_result_is_final_and_may_be_null(): void
    {
        $this->fields();
        PrintField::where('placeholder', 'PRICE')->update(['data_type' => 'T']);
        $transformer = new class implements FieldTransformer
        {
            public function transform(mixed $value, mixed $record, Model&PrintKitModel $field, ResolutionContext $context): mixed
            {
                return null;
            }
        };
        app(FieldTransformerRegistry::class)->register('INV', 'ET', 'PRICE', $transformer);
        $result = app(LabelResolver::class)->resolveWithRecord($this->format(), 'mysql', ['descripcio' => 'X', 'precio1' => null]);
        $this->assertNull($result->values()['PRICE']);
    }

    public function test_t_rejects_mutable_result(): void
    {
        $this->fields();
        PrintField::where('placeholder', 'PRICE')->update(['data_type' => 'T']);
        $transformer = new class implements FieldTransformer
        {
            public function transform(mixed $value, mixed $record, Model&PrintKitModel $field, ResolutionContext $context): mixed
            {
                return new \stdClass;
            }
        };
        app(FieldTransformerRegistry::class)->register('INV', 'ET', 'PRICE', $transformer);
        $this->expectException(InvalidResolvedValue::class);
        app(LabelResolver::class)->resolveWithRecord($this->format(), 'mysql', ['descripcio' => 'X', 'precio1' => 1]);
    }

    public function test_wildcard_is_valid_for_design_but_exact_unusable_shadows_it(): void
    {
        $this->field(['category' => '*']);
        $this->field(['category' => '*', 'placeholder' => 'PRICE', 'associated_field' => 'precio1']);
        $paper = PaperSize::create(['name' => 'Test', 'width' => 10, 'height' => 10]);
        $format = $this->format();
        $format->paper_size_id = $paper->id;
        $format->design()->validateFor($format);
        $this->assertTrue(true);
        $this->field(['usable_in_detail' => false]);
        $this->expectException(InvalidDesign::class);
        $format->design()->validateFor($format);
    }

    public function test_catalog_inherits_format_storage_connection(): void
    {
        $this->fields();
        config(['database.connections.empty' => config('database.connections.testing')]);
        $format = $this->format();
        $format->setConnection('testing');
        config(['database.default' => 'empty']);
        try {
            $this->assertSame('X', app(LabelResolver::class)->resolveWithRecord($format, 'mysql', ['descripcio' => 'X', 'precio1' => 1])->values()['DESCRIPTION']);
        } finally {
            config(['database.default' => 'testing']);
        }
    }

    public function test_explicit_catalog_connection_wins_over_format_connection(): void
    {
        $this->fields();
        config(['database.connections.empty' => config('database.connections.testing'), 'print-kit.database.connection' => 'testing']);
        $format = $this->format();
        $format->setConnection('empty');
        $this->assertSame('X', app(LabelResolver::class)->resolveWithRecord($format, 'mysql', ['descripcio' => 'X', 'precio1' => 1])->values()['DESCRIPTION']);
    }

    public function test_collection_and_resolved_design_are_immutable(): void
    {
        $label = app(LabelResolver::class)->resolveWithValues($this->format(), ['DESCRIPTION' => 'x', 'PRICE' => 1]);
        $collection = new ResolvedLabelCollection($label);
        $array = $collection->toArray();
        $array[0]['elements'][0]['value'] = 'changed';
        $this->assertSame('x', $collection->toArray()[0]['elements'][0]['value']);
        $this->expectException(\Error::class);
        $label->design = LabelDesign::empty();
    }

    private function format(): PrintFormat
    {
        return new PrintFormat(['detail' => ['schema_version' => 1, 'elements' => [
            ['id' => 'name', 'type' => 'field', 'field' => 'DESCRIPTION', 'x' => 0, 'y' => 0, 'width' => 2, 'height' => 1, 'style' => ['font_size' => 10]],
            ['id' => 'caption', 'type' => 'text', 'text' => 'Precio Bs.', 'x' => 0, 'y' => 1, 'width' => 2, 'height' => 1],
            ['id' => 'price', 'type' => 'field', 'field' => 'PRICE', 'x' => 0, 'y' => 2, 'width' => 2, 'height' => 1],
            ['id' => 'repeat', 'type' => 'field', 'field' => 'DESCRIPTION', 'x' => 0, 'y' => 3, 'width' => 2, 'height' => 1],
        ]]]);
    }

    private function field(array $attributes = []): PrintField
    {
        return PrintField::create(array_replace(['placeholder' => 'DESCRIPTION', 'display_name' => 'Descripción', 'associated_field' => 'descripcio', 'usable_in_detail' => true], $attributes));
    }

    private function fields(): void
    {
        $this->field();
        $this->field(['placeholder' => 'PRICE', 'associated_field' => 'precio1', 'data_type' => 'D,2']);
    }

    private function watch(): void
    {
        DB::connection()->flushQueryLog();
        DB::connection()->enableQueryLog();
    }

    private function setupSource(): void
    {
        config(['database.connections.mysql' => config('database.connections.testing')]);
        Schema::connection('mysql')->create('I00001', function ($table) {
            $table->string('producto')->primary();
            $table->string('descripcio');
            $table->string('precio1');
            $table->string('marca');
        });
        DB::connection('mysql')->table('I00001')->insert([
            ['producto' => 'P001', 'descripcio' => 'Uno', 'precio1' => '125.555', 'marca' => 'A'],
            ['producto' => 'P002', 'descripcio' => 'Dos', 'precio1' => '-5', 'marca' => 'B'],
        ]);
        DB::connection('mysql')->flushQueryLog();
        DB::connection('mysql')->enableQueryLog();
    }

    public function test_values_are_final_with_zero_queries_and_unchanged_design(): void
    {
        $format = $this->format();
        $before = $format->getAttributes();
        config(['print-kit.systems' => false, 'print-kit.drivers' => false, 'print-kit.transformers' => false]);
        $this->watch();
        $result = app(LabelResolver::class)->resolveWithValues($format, ['DESCRIPTION' => null, 'PRICE' => 'Bs. 125,50', 'unused' => new \stdClass]);
        $this->assertCount(0, DB::getQueryLog());
        $this->assertSame($before, $format->getAttributes());
        $elements = $result->toArray()['elements'];
        $this->assertNull($elements[0]['value']);
        $this->assertSame('Bs. 125,50', $elements[2]['value']);
        $this->assertSame($format->detail['elements'][1], $elements[1]);
        $this->assertSame(['name', 'caption', 'price', 'repeat'], array_column($elements, 'id'));
        $this->assertSame('2.0000', $elements[0]['width']);
        $this->assertSame(['font_size' => 10], $elements[0]['style']);
        $this->assertArrayNotHasKey('value', $result->design->toArray()['elements'][0]);
        $this->assertSame($result->toArray(), json_decode(json_encode($result), true));
        $copy = $result->values();
        $copy['PRICE'] = 'changed';
        $this->assertSame('Bs. 125,50', $result->values()['PRICE']);
        $this->expectException(\Error::class);
        $result->context->system = 'POS';
    }

    public function test_missing_provided_value(): void
    {
        $this->expectException(MissingProvidedValue::class);
        app(LabelResolver::class)->resolveWithValues($this->format(), ['PRICE' => null]);
    }

    public function test_non_scalar_provided_value(): void
    {
        $this->expectException(InvalidResolvedValue::class);
        app(LabelResolver::class)->resolveWithValues($this->format(), ['DESCRIPTION' => [], 'PRICE' => 1]);
    }

    #[DataProvider('recordKinds')]
    public function test_record_shapes_without_source_connection(string $kind): void
    {
        $this->fields();
        $data = ['descripcio' => null, 'precio1' => '125.5'];
        $record = match ($kind) {
            'array' => $data, 'object' => (object) $data, 'access' => new \ArrayObject($data), 'eloquent' => (new class extends Model {})->setRawAttributes($data)
        };
        $format = $this->format();
        $before = $format->getAttributes();
        $this->watch();
        $result = app(LabelResolver::class)->resolveWithRecord($format, 'mysql', $record);
        $this->assertSame(['DESCRIPTION' => '', 'PRICE' => '125.50'], $result->values());
        $this->assertCount(1, DB::getQueryLog());
        $this->assertSame($before, $format->getAttributes());
        $this->assertSame(ResolutionMode::ProvidedRecord, $result->context->mode);
    }

    public static function recordKinds(): array
    {
        return [['array'], ['object'], ['access'], ['eloquent']];
    }

    public function test_missing_physical_attribute_is_not_null(): void
    {
        $this->fields();
        $this->expectException(AssociatedFieldNotFound::class);
        app(LabelResolver::class)->resolveWithRecord($this->format(), 'mysql', ['precio1' => 1]);
    }

    public function test_exact_category_shadows_wildcard_and_drivers_are_isolated(): void
    {
        $this->field(['category' => '*', 'associated_field' => 'fallback']);
        $exact = $this->field(['associated_field' => 'exact']);
        $other = $this->field(['driver' => 'mssql']);
        $resolver = app(PrintFieldResolver::class);
        $this->assertSame($exact->id, $resolver->resolve('mysql', 'INV', 'ET', 'DESCRIPTION')->id);
        $this->assertSame($other->id, $resolver->resolve('mssql', 'INV', 'ET', 'DESCRIPTION')->id);
        $this->assertCount(1, $resolver->fieldsFor('mysql', 'INV', 'ET'));
        $exact->delete();
        $this->assertSame('fallback', $resolver->resolve('mysql', 'INV', 'ET', 'DESCRIPTION')->associated_field);
    }

    public function test_unusable_exact_definition_does_not_fall_back(): void
    {
        $this->field(['category' => '*']);
        $this->field(['usable_in_detail' => false]);
        $this->field(['placeholder' => 'PRICE', 'associated_field' => 'precio1']);
        $this->expectException(PrintFieldNotFound::class);
        app(LabelResolver::class)->resolveWithRecord($this->format(), 'mysql', ['descripcio' => 'X', 'precio1' => 1]);
    }

    public function test_unique_constraint_is_enforced(): void
    {
        $this->field();
        $this->expectException(QueryException::class);
        $this->field();
    }

    public function test_duplicate_detection_without_consumer_unique_index(): void
    {
        Schema::table('print_fields', fn ($table) => $table->dropUnique('print_fields_identity_unique'));
        $this->field();
        $this->field();
        $this->expectException(DuplicatePrintFieldDefinition::class);
        app(PrintFieldResolver::class)->fieldsFor('mysql', 'INV', 'ET');
    }

    #[DataProvider('invalidFieldAttributes')]
    public function test_invalid_field_definition(array $attributes): void
    {
        $this->expectException(ValidationException::class);
        $this->field($attributes);
    }

    public static function invalidFieldAttributes(): array
    {
        return [[['driver' => '*']], [['system' => '*']], [['system' => '***']], [['category' => 'E*']], [['associated_field' => 'precio * tasa']], [['associated_field' => 'UPPER(name)']]];
    }

    public function test_scope_replacement_and_physical_aliases(): void
    {
        $this->fields();
        Schema::rename('print_fields', 'catalog');
        foreach (['driver' => 'conexion', 'placeholder' => 'comodin', 'system' => 'sistema', 'category' => 'categoria', 'associated_field' => 'columna', 'data_type' => 'tipo', 'usable_in_detail' => 'usable'] as $canonical => $physical) {
            Schema::table('catalog', fn ($table) => $table->renameColumn($canonical, $physical));
            config(["print-kit.columns.fields.$canonical" => $physical]);
        }
        config(['print-kit.tables.fields' => 'catalog', 'print-kit.models.field' => ScopedField::class]);
        DB::table('catalog')->update(['description' => 'allowed']);
        $format = new IndependentFormat(['system' => 'INV', 'category' => 'ET', 'detail' => $this->format()->detail]);
        $this->assertSame('125.50', app(LabelResolver::class)->resolveWithRecord($format, 'mysql', ['descripcio' => 'x', 'precio1' => '125.5'])->values()['PRICE']);
        DB::table('catalog')->where('comodin', 'DESCRIPTION')->update(['description' => 'hidden']);
        $this->expectException(PrintFieldNotFound::class);
        app(LabelResolver::class)->resolveWithRecord($format, 'mysql', ['descripcio' => 'x', 'precio1' => '125.5']);
    }

    public function test_database_batch_preserves_order_duplicates_and_query_counts(): void
    {
        $this->fields();
        $this->setupSource();
        $this->watch();
        $result = app(LabelResolver::class)->resolveByKeys($this->format(), 'mysql', ['P002', 'P001', 'P002']);
        $labels = iterator_to_array($result);
        $this->assertCount(3, $result);
        $this->assertSame(['P002', 'P001', 'P002'], array_map(fn ($label) => $label->context->key, $labels));
        $this->assertSame(['-5.00', '125.56', '-5.00'], array_map(fn ($label) => $label->values()['PRICE'], $labels));
        $this->assertCount(1, DB::getQueryLog());
        $queries = DB::connection('mysql')->getQueryLog();
        $this->assertCount(1, $queries);
        $this->assertSame(['P002', 'P001'], $queries[0]['bindings']);
        $this->assertStringContainsString('select *', $queries[0]['query']);
        $this->assertSame($result->toArray(), json_decode(json_encode($result), true));
    }

    public function test_database_single_and_missing_key_context(): void
    {
        $this->fields();
        $this->setupSource();
        $resolver = app(LabelResolver::class);
        $this->assertSame('Uno', $resolver->resolveByKey($this->format(), 'mysql', 'P001')->values()['DESCRIPTION']);
        try {
            $resolver->resolveByKeys($this->format(), 'mysql', ['P001', "x' OR 1=1 --"]);
            $this->fail('Debe faltar la clave.');
        } catch (RecordNotFound $e) {
            $this->assertSame("x' OR 1=1 --", $e->keyValue);
            $this->assertSame('producto', $e->keyField);
            $this->assertSame('mysql', $e->driver);
            $this->assertSame('INV', $e->system);
            $this->assertStringNotContainsString($e->keyValue, $e->getMessage());
        }
        $this->assertSame(["x' OR 1=1 --"], array_slice(DB::connection('mysql')->getQueryLog()[1]['bindings'], 1));
    }

    public function test_unknown_driver_before_any_query(): void
    {
        $this->watch();
        try {
            app(LabelResolver::class)->resolveByKey($this->format(), 'arbitrary', 'P001');
            $this->fail();
        } catch (UnknownDriver) {
            $this->assertCount(0, DB::getQueryLog());
        }
    }

    public function test_unknown_system_before_any_query(): void
    {
        $format = $this->format();
        $format->system = 'POS';
        $this->expectException(UnknownSystem::class);
        app(LabelResolver::class)->resolveByKey($format, 'mysql', 'P001');
    }

    public function test_empty_batch_does_not_query(): void
    {
        $this->watch();
        $this->assertCount(0, app(LabelResolver::class)->resolveByKeys($this->format(), 'mysql', []));
        $this->assertCount(0, DB::getQueryLog());
    }

    #[DataProvider('invalidKeys')]
    public function test_invalid_keys(mixed $key): void
    {
        $this->expectException(InvalidResolvedValue::class);
        app(LabelResolver::class)->resolveByKey($this->format(), 'mysql', $key);
    }

    public static function invalidKeys(): array
    {
        return [[null], [true], [1.5], [[]], ['']];
    }

    public function test_transformer_required_for_t(): void
    {
        $this->fields();
        PrintField::where('placeholder', 'DESCRIPTION')->update(['data_type' => 'T']);
        $this->expectException(TransformerNotFound::class);
        app(LabelResolver::class)->resolveWithRecord($this->format(), 'mysql', ['descripcio' => 'x', 'precio1' => 1]);
    }

    public function test_transformer_receives_null_full_record_and_context_before_formatter(): void
    {
        $this->fields();
        PrintField::where('placeholder', 'DESCRIPTION')->update(['data_type' => 'T']);
        $transformer = new class implements FieldTransformer
        {
            public array $calls = [];

            public function transform(mixed $value, mixed $record, Model&PrintKitModel $field, ResolutionContext $context): mixed
            {
                $this->calls[] = [$value, $record, $field->placeholder, $context];

                return $field->placeholder === 'PRICE' ? $record['extra'] : 'Final '.$record['marca'];
            }
        };
        $registry = app(FieldTransformerRegistry::class);
        $registry->register('INV', 'ET', 'DESCRIPTION', $transformer);
        $registry->register('INV', 'ET', 'PRICE', $transformer);
        $record = ['descripcio' => null, 'precio1' => '1', 'extra' => '125.555', 'marca' => 'Truper'];
        $result = app(LabelResolver::class)->resolveWithRecord($this->format(), 'mysql', $record);
        $this->assertSame(['DESCRIPTION' => 'Final Truper', 'PRICE' => '125.56'], $result->values());
        $this->assertCount(2, $transformer->calls);
        $this->assertNull($transformer->calls[0][0]);
        $this->assertSame($record, $transformer->calls[0][1]);
        $this->assertSame('DESCRIPTION', $transformer->calls[0][2]);
        $this->assertSame('mysql', $transformer->calls[0][3]->driver);
    }
}
