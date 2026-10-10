<?php

declare(strict_types=1);

namespace Ahinest\LaravelPrintKit\Tests;

use Ahinest\LaravelPrintKit\Models\PaperSize;
use Ahinest\LaravelPrintKit\Models\PrintField;
use Ahinest\LaravelPrintKit\Models\PrintFormat;
use Ahinest\LaravelPrintKit\PrintKitServiceProvider;
use Ahinest\LaravelPrintKit\Support\ColumnResolver;
use Ahinest\LaravelPrintKit\Support\ModelResolver;
use Ahinest\LaravelPrintKit\Tests\Fixtures\CustomField;
use Ahinest\LaravelPrintKit\Tests\Fixtures\CustomFormat;
use Ahinest\LaravelPrintKit\Tests\Fixtures\IndependentPaperSize;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;

class ConfigurationTest extends ConfiguredTestCase
{
    public function test_partial_configuration_is_merged_recursively(): void
    {
        config(['print-kit' => ['columns' => ['fields' => ['description' => 'descripcion']]]]);
        (new PrintKitServiceProvider($this->app))->register();
        $this->assertSame('descripcion', (new PrintField)->column('description'));
        $this->assertSame('placeholder', (new PrintField)->column('placeholder'));
        $this->assertNull(config('print-kit.defaults.fields.system'));
        $this->assertSame(PrintFormat::class, config('print-kit.models.format'));
    }

    public function test_model_substitution_in_resolver_and_relationships(): void
    {
        config(['print-kit.models.field' => CustomField::class, 'print-kit.models.paper_size' => IndependentPaperSize::class, 'print-kit.models.format' => CustomFormat::class]);
        $resolver = $this->app->make(ModelResolver::class);
        $field = $resolver->make('field')->newQuery()->create(['placeholder' => 'CODE', 'display_name' => 'code', 'associated_field' => 'code']);
        $this->assertInstanceOf(CustomField::class, $field);
        $this->assertSame('CODE', $field->fresh()->display_name);
        $paper = $resolver->make('paper_size')->newQuery()->create(['name' => 'Custom', 'width' => '5', 'height' => '3']);
        $format = PrintFormat::create(['code' => 'CUSTOM', 'name' => 'Custom', 'paper_size_id' => $paper->id]);
        $this->assertInstanceOf(IndependentPaperSize::class, $format->fresh()->paperSize);
        $this->assertInstanceOf(CustomFormat::class, PaperSize::find($paper->id)->formats->first());
    }

    public function test_independent_model_works_with_renamed_columns(): void
    {
        Schema::rename('print_paper_sizes', 'own_sizes');
        Schema::table('own_sizes', function ($table): void {
            $table->renameColumn('id', 'clave');
            $table->renameColumn('width', 'ancho');
        });
        config(['print-kit.tables.paper_sizes' => 'own_sizes', 'print-kit.columns.paper_sizes.id' => 'clave', 'print-kit.columns.paper_sizes.width' => 'ancho', 'print-kit.models.paper_size' => IndependentPaperSize::class]);
        $paper = $this->app->make(ModelResolver::class)->make('paper_size')->newQuery()->create(['name' => 'Custom', 'width' => '5.1234', 'height' => '3']);
        $format = PrintFormat::create(['code' => 'CUSTOM', 'name' => 'Custom', 'paper_size_id' => $paper->id]);
        $related = PrintFormat::with('paperSize')->first()->paperSize;
        $this->assertInstanceOf(IndependentPaperSize::class, $related);
        $this->assertSame('5.1234', $related->width);
        $this->assertSame($paper->id, $format->paperSize->id);
    }

    public function test_invalid_model_class_is_rejected(): void
    {
        config(['print-kit.models.field' => Model::class]);
        $this->expectException(InvalidArgumentException::class);
        $this->app->make(ModelResolver::class)->make('field');
    }

    public function test_wrong_model_entity_is_rejected(): void
    {
        config(['print-kit.models.field' => PaperSize::class]);
        $this->expectException(InvalidArgumentException::class);
        $this->app->make(ModelResolver::class)->make('field');
    }

    public function test_ambiguous_column_mapping_is_rejected(): void
    {
        config(['print-kit.columns.fields.description' => 'display_name']);
        $this->expectException(InvalidArgumentException::class);
        (new PrintField)->column('description');
    }

    public function test_sql_expression_in_mapping_is_rejected(): void
    {
        config(['print-kit.columns.fields.description' => 'description; DROP TABLE x']);
        $this->expectException(InvalidArgumentException::class);
        (new PrintField)->column('description');
    }

    public function test_unsafe_table_identifier_is_rejected(): void
    {
        config(['print-kit.tables.fields' => 'fields AS x']);
        $this->expectException(InvalidArgumentException::class);
        (new PrintField)->getTable();
    }

    public function test_unrelated_qualified_columns_are_not_rewritten(): void
    {
        config(['print-kit.columns.fields.id' => 'clave']);
        $resolver = $this->app->make(ColumnResolver::class);
        $this->assertSame('external.id', $resolver->queryColumn('fields', 'external.id'));
        $this->assertSame('print_fields.clave', $resolver->queryColumn('fields', 'print_fields.id'));
    }

    public function test_storage_connection_is_separate_from_source_driver(): void
    {
        config(['database.connections.package' => config('database.connections.testing'), 'print-kit.database.connection' => 'package']);
        foreach (glob(__DIR__.'/../database/migrations/*.php') as $path) {
            (require $path)->up();
        }
        $field = PrintField::create(['driver' => 'source_connection', 'placeholder' => 'CODE', 'display_name' => 'Code', 'associated_field' => 'code']);
        $this->assertSame('package', $field->getConnection()->getName());
        $this->assertSame('source_connection', $field->driver);
        $this->assertSame(1, DB::connection('package')->table('print_fields')->count());
        $this->assertSame(0, DB::connection('testing')->table('print_fields')->count());
    }
}
