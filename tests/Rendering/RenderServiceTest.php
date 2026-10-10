<?php

declare(strict_types=1);

namespace Ahinest\LaravelPrintKit\Tests\Rendering;

use Ahinest\LaravelPrintKit\Data\DataTypeFormatter;
use Ahinest\LaravelPrintKit\Data\FieldTransformerRegistry;
use Ahinest\LaravelPrintKit\Data\LabelResolver;
use Ahinest\LaravelPrintKit\Data\ResolvedLabelCollection;
use Ahinest\LaravelPrintKit\Models\PaperSize;
use Ahinest\LaravelPrintKit\Models\PrintFormat;
use Ahinest\LaravelPrintKit\Rendering\Exceptions\InvalidRenderContext;
use Ahinest\LaravelPrintKit\Rendering\Exceptions\UnsupportedRenderCategory;
use Ahinest\LaravelPrintKit\Rendering\HtmlDocumentRenderer;
use Ahinest\LaravelPrintKit\Rendering\LabelHtmlRenderer;
use Ahinest\LaravelPrintKit\Rendering\LabelRenderContextFactory;
use Ahinest\LaravelPrintKit\Rendering\LabelRenderService;
use Ahinest\LaravelPrintKit\Tests\ConfiguredTestCase;
use Ahinest\LaravelPrintKit\Tests\Fixtures\IndependentFormat;
use Ahinest\LaravelPrintKit\Tests\Fixtures\IndependentPaperSize;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;

/** Comprueba consultas separadas de papel y compatibilidad con el almacenamiento configurable. */
class RenderServiceTest extends ConfiguredTestCase
{
    private function format(): PrintFormat
    {
        $paper = PaperSize::create(['name' => 'Etiqueta', 'width' => '5', 'height' => '3']);

        return new PrintFormat(['paper_size_id' => $paper->id, 'detail' => ['schema_version' => 1, 'elements' => [
            ['id' => 'f', 'type' => 'field', 'field' => 'VALUE', 'x' => '0.2', 'y' => '0.3', 'width' => 2, 'height' => '0.5'],
        ]]]);
    }

    private function watch(): void
    {
        DB::connection()->flushQueryLog();
        DB::connection()->enableQueryLog();
    }

    private function denyDataResolution(): void
    {
        foreach ([LabelResolver::class, DataTypeFormatter::class, FieldTransformerRegistry::class] as $class) {
            // Laravel 10 resuelve inmediatamente un binding sustituido si conserva la marca de resolución.
            unset($this->app[$class]);
            $this->app->bind($class, fn () => throw new \LogicException('El render no debe resolver datos.'));
        }
        config(['print-kit.drivers' => [], 'print-kit.systems' => [], 'print-kit.models.field' => 'InvalidFieldClass']);
    }

    public function test_service_queries_only_paper_and_preserves_input(): void
    {
        $format = $this->format();
        $label = app(LabelResolver::class)->resolveWithValues($format, ['VALUE' => 'Bs. 125,50']);
        $before = $format->getAttributes();
        $resolvedBefore = $label->toArray();
        $this->denyDataResolution();
        $this->watch();
        $document = app(LabelRenderService::class)->renderDocument($format, $label);
        $queries = DB::getQueryLog();
        $this->assertCount(1, $queries);
        $this->assertStringContainsString('print_paper_sizes', $queries[0]['query']);
        $this->assertStringContainsString('Bs. 125,50', $document->html());
        $this->assertSame($before, $format->getAttributes());
        $this->assertSame($resolvedBefore, $label->toArray());
        $this->assertFalse($format->relationLoaded('paperSize'));
    }

    public function test_loaded_paper_and_pure_renderers_use_zero_queries(): void
    {
        $format = $this->format();
        $format->load('paperSize');
        $label = app(LabelResolver::class)->resolveWithValues($format, ['VALUE' => '<b>ñ</b>']);
        $this->denyDataResolution();
        $this->watch();
        $context = app(LabelRenderContextFactory::class)->create($format);
        $rendered = app(LabelHtmlRenderer::class)->render($context, $label);
        $document = app(HtmlDocumentRenderer::class)->render($rendered);
        $this->assertCount(0, DB::getQueryLog());
        $this->assertStringContainsString('&lt;b&gt;ñ&lt;/b&gt;', $document->html());
    }

    public function test_explicit_paper_avoids_query(): void
    {
        $format = $this->format();
        $paper = PaperSize::findOrFail($format->paper_size_id);
        $this->watch();
        $context = app(LabelRenderContextFactory::class)->create($format, $paper);
        $this->assertCount(0, DB::getQueryLog());
        $this->assertSame('5.0000', (string) $context->width);
    }

    public function test_collection_uses_one_paper_query_and_preserves_order(): void
    {
        $format = $this->format();
        $resolver = app(LabelResolver::class);
        $labels = [];
        foreach (['P003', 'P001', 'P002', 'P001'] as $value) {
            $labels[] = $resolver->resolveWithValues($format, ['VALUE' => $value]);
        }
        $this->denyDataResolution();
        $this->watch();
        $document = app(LabelRenderService::class)->renderCollection($format, new ResolvedLabelCollection(...$labels));
        $this->assertCount(1, DB::getQueryLog());
        $this->assertSame(4, $document->count);
        preg_match_all('/>(P[0-9]+)<\/div>/', $document->html(), $matches);
        $this->assertSame(['P003', 'P001', 'P002', 'P001'], $matches[1]);
    }

    public function test_empty_collection_has_explicit_physical_context(): void
    {
        $format = $this->format();
        $this->watch();
        $document = app(LabelRenderService::class)->renderCollection($format, new ResolvedLabelCollection);
        $this->assertCount(1, DB::getQueryLog());
        $this->assertSame(0, $document->count);
        $this->assertSame('5.0000', $document->width);
    }

    public function test_stale_loaded_paper_cannot_silently_follow_changed_foreign_key(): void
    {
        $format = $this->format();
        $format->load('paperSize');
        $format->paper_size_id = 999;
        $this->expectException(InvalidRenderContext::class);
        app(LabelRenderContextFactory::class)->create($format);
    }

    public function test_missing_paper_is_domain_error(): void
    {
        $format = $this->format();
        $format->paper_size_id = 999;
        $this->expectException(InvalidRenderContext::class);
        app(LabelRenderContextFactory::class)->create($format);
    }

    public function test_explicit_wrong_paper_is_rejected(): void
    {
        $format = $this->format();
        $paper = PaperSize::create(['name' => 'Otro', 'width' => 5, 'height' => 3]);
        $this->expectException(InvalidRenderContext::class);
        app(LabelRenderContextFactory::class)->create($format, $paper);
    }

    public function test_loaded_null_is_not_silently_queried_again(): void
    {
        $format = $this->format();
        $format->setRelation('paperSize', null);
        $this->watch();
        try {
            app(LabelRenderContextFactory::class)->create($format);
            $this->fail();
        } catch (InvalidRenderContext) {
            $this->assertCount(0, DB::getQueryLog());
        }
    }

    public function test_changed_design_is_incompatible_with_previously_resolved_label(): void
    {
        $format = $this->format();
        $label = app(LabelResolver::class)->resolveWithValues($format, ['VALUE' => 'x']);
        $detail = $format->detail;
        $detail['elements'][0]['x'] = '0.25';
        $format->detail = $detail;
        $this->expectException(InvalidRenderContext::class);
        app(LabelRenderService::class)->render($format, $label);
    }

    public function test_changed_paper_geometry_is_revalidated_without_catalog(): void
    {
        $format = $this->format();
        $label = app(LabelResolver::class)->resolveWithValues($format, ['VALUE' => 'x']);
        PaperSize::where('id', $format->paper_size_id)->update(['width' => '1']);
        $this->denyDataResolution();
        $this->expectException(InvalidRenderContext::class);
        app(LabelRenderService::class)->render($format, $label);
    }

    public function test_replacement_models_aliases_and_scope_are_honored(): void
    {
        $format = $this->format();
        Schema::rename('print_paper_sizes', 'papers');
        foreach (['id' => 'paper_key', 'width' => 'ancho', 'height' => 'alto'] as $canonical => $physical) {
            Schema::table('papers', fn ($table) => $table->renameColumn($canonical, $physical));
            config(["print-kit.columns.paper_sizes.$canonical" => $physical]);
        }
        config(['print-kit.tables.paper_sizes' => 'papers', 'print-kit.models.paper_size' => IndependentPaperSize::class]);
        $independent = (new IndependentFormat)->setRawAttributes($format->getAttributes());
        $context = app(LabelRenderContextFactory::class)->create($independent);
        $this->assertSame('5.0000', (string) $context->width);
        IndependentPaperSize::addGlobalScope('hidden', fn ($query) => $query->where('is_active', false));
        $this->expectException(InvalidRenderContext::class);
        app(LabelRenderContextFactory::class)->create($independent);
    }

    public function test_paper_connection_inherits_format_storage(): void
    {
        $format = $this->format();
        $format->setConnection('testing');
        config(['database.connections.empty' => config('database.connections.testing'), 'database.default' => 'empty']);
        try {
            $this->assertSame('5.0000', (string) app(LabelRenderContextFactory::class)->create($format)->width);
        } finally {
            config(['database.default' => 'testing']);
        }
    }

    public function test_explicit_package_connection_wins_over_format_connection(): void
    {
        $format = $this->format();
        config(['database.connections.empty' => config('database.connections.testing'), 'print-kit.database.connection' => 'testing']);
        $format->setConnection('empty');
        $this->assertSame('5.0000', (string) app(LabelRenderContextFactory::class)->create($format)->width);
    }

    public function test_paper_from_other_connection_is_rejected_even_with_same_id(): void
    {
        $format = $this->format();
        $paper = PaperSize::findOrFail($format->paper_size_id);
        $paper->setConnection('other');
        $this->expectException(InvalidRenderContext::class);
        app(LabelRenderContextFactory::class)->create($format, $paper);
    }

    #[DataProvider('invalidAttributes')]
    public function test_invalid_raw_format_context(array $changes): void
    {
        $format = $this->format();
        $format->setRawAttributes(array_replace($format->getAttributes(), $changes));
        $this->expectException(InvalidRenderContext::class);
        app(LabelRenderContextFactory::class)->create($format);
    }

    public static function invalidAttributes(): array
    {
        return [[['orientation' => 'X']], [['margins' => null]], [['detail' => null]], [['paper_size_id' => null]], [['paper_size_id' => 0]], [['system' => '*']], [['detail' => '{"schema_version":99,"elements":[]}']]];
    }

    public function test_unsupported_category_is_rejected_before_query(): void
    {
        $format = $this->format();
        $format->category = 'RP';
        $this->watch();
        try {
            app(LabelRenderContextFactory::class)->create($format);
            $this->fail();
        } catch (UnsupportedRenderCategory) {
            $this->assertCount(0, DB::getQueryLog());
        }
    }

    public function test_wrong_entity_is_rejected(): void
    {
        $paper = PaperSize::create(['name' => 'x', 'width' => 5, 'height' => 3]);
        $this->expectException(InvalidRenderContext::class);
        app(LabelRenderContextFactory::class)->create($paper);
    }

    public function test_context_freezes_loaded_paper_snapshot(): void
    {
        $format = $this->format();
        $format->load('paperSize');
        $context = app(LabelRenderContextFactory::class)->create($format);
        $format->paperSize->width = '99';
        $this->assertSame('5.0000', (string) $context->width);
        $this->expectException(\Error::class);
        $context->margins['left'] = '99';
    }
}
