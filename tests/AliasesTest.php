<?php

declare(strict_types=1);

namespace Ahinest\LaravelPrintKit\Tests;

use Ahinest\LaravelPrintKit\Design\InvalidDesign;
use Ahinest\LaravelPrintKit\Design\LabelDesign;
use Ahinest\LaravelPrintKit\Models\PaperSize;
use Ahinest\LaravelPrintKit\Models\PrintField;
use Ahinest\LaravelPrintKit\Models\PrintFormat;
use Ahinest\LaravelPrintKit\Support\ModelResolver;
use Ahinest\LaravelPrintKit\Tests\Fixtures\CustomFormat;
use Ahinest\LaravelPrintKit\Tests\Fixtures\IndependentPaperSize;
use Ahinest\LaravelPrintKit\Tests\Fixtures\ScopedField;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AliasesTest extends ConfiguredTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $maps = [
            'fields' => ['id' => 'clave', 'placeholder' => 'codigo', 'description' => 'descripcion', 'display_name' => 'nombre_visible', 'usable_in_detail' => 'en_detalle', 'system' => 'sistema', 'category' => 'categoria', 'created_at' => 'creado', 'updated_at' => 'actualizado'],
            'paper_sizes' => ['id' => 'papel_clave', 'name' => 'nombre_papel', 'width' => 'ancho', 'height' => 'alto', 'is_active' => 'activo', 'created_at' => 'creado', 'updated_at' => 'actualizado'],
            'formats' => ['id' => 'formato_clave', 'code' => 'codigo_formato', 'paper_size_id' => 'papel_id', 'copies' => 'copias', 'detail' => 'detalle', 'margins' => 'margenes', 'preview' => 'vista_previa', 'created_at' => 'creado', 'updated_at' => 'actualizado'],
        ];
        foreach ($maps as $entity => $map) {
            $old = config("print-kit.tables.$entity");
            $table = 'custom_'.$entity;
            Schema::rename($old, $table);
            foreach ($map as $canonical => $physical) {
                Schema::table($table, fn ($blueprint) => $blueprint->renameColumn($canonical, $physical));
                config(["print-kit.columns.$entity.$canonical" => $physical]);
            }
            config(["print-kit.tables.$entity" => $table]);
        }
    }

    public function test_create_read_select_where_update_delete_and_serialization(): void
    {
        $field = PrintField::create(['placeholder' => 'DESCRIPTION', 'display_name' => 'Descripción', 'description' => 'Inicial', 'associated_field' => 'descripcio']);
        $this->assertSame('descripcion', $field->column('description'));
        $this->assertSame('DESCRIPTION', DB::table('custom_fields')->value('codigo'));
        $this->assertNotNull(DB::table('custom_fields')->value('creado'));
        $this->assertSame($field->id, PrintField::findOrFail($field->id)->id);
        $loaded = PrintField::where('placeholder', 'DESCRIPTION')->select('id', 'description', 'display_name')->firstOrFail();
        $this->assertSame('Inicial', $loaded->description);
        $this->assertSame('Descripción', $loaded->toArray()['display_name']);
        $this->assertArrayNotHasKey('nombre_visible', $loaded->toArray());
        $this->assertSame('Inicial', PrintField::query()->value('description'));
        $this->assertSame(['Inicial'], PrintField::query()->pluck('description')->all());
        $this->assertSame([false], PrintField::query()->pluck('usable_in_detail')->all());
        $this->assertSame('DESCRIPTION', PrintField::query()->first(['placeholder'])->placeholder);
        $field->update(['description' => 'Guardado']);
        $this->assertSame('Guardado', DB::table('custom_fields')->value('descripcion'));
        PrintField::where(['placeholder' => 'DESCRIPTION'])->update(['description' => 'Masivo', 'usable_in_detail' => true]);
        $this->assertTrue($field->fresh()->usable_in_detail);
        $this->assertSame('Masivo', $field->fresh()->description);
        $this->assertSame(1, PrintField::where('description', 'Masivo')->delete());
        $this->assertSame(0, DB::table('custom_fields')->count());
    }

    public function test_nested_filters_order_aggregate_cursor_and_explicit_alias(): void
    {
        foreach (['B', 'A'] as $code) {
            PrintField::create(['placeholder' => $code, 'display_name' => $code, 'associated_field' => 'source']);
        }
        $query = PrintField::where(fn ($q) => $q->whereIn('placeholder', ['A', 'B'])->whereNull('description'));
        $this->assertSame(2, $query->count('id'));
        $this->assertSame(['A', 'B'], $query->orderBy('placeholder')->pluck('placeholder')->all());
        $this->assertSame('A', PrintField::orderBy('placeholder')->cursor(['placeholder'])->first()->placeholder);
        $this->assertSame('A', PrintField::where('placeholder', 'A')->select('placeholder as chosen')->first()->chosen);
        $this->assertSame(2, PrintField::whereColumn('placeholder', 'display_name')->count());
        $this->assertSame(2, PrintField::whereDate('created_at', now()->toDateString())->count());
        $this->assertSame(2, PrintField::whereBetween('id', [1, 2])->count());
        $this->assertSame(2, PrintField::where('display_name', 'like', '%')->count());
        $this->assertSame(2, PrintField::whereLike('display_name', '%')->count());
        $this->assertSame('A', PrintField::select('placeholder')->groupBy('placeholder')->having('placeholder', 'A')->first()->placeholder);
    }

    public function test_aliases_in_relationships_eager_loading_existence_and_association(): void
    {
        $paper = PaperSize::create(['name' => 'Etiqueta', 'width' => '5.25', 'height' => '3']);
        $format = $paper->formats()->create(['code' => 'REL', 'name' => 'Relación']);
        $this->assertSame($paper->id, DB::table('custom_formats')->value('papel_id'));
        $this->assertTrue($format->fresh()->paperSize->is($paper));
        $this->assertTrue(PrintFormat::with('paperSize')->first()->paperSize->is($paper));
        $this->assertTrue(PaperSize::with('formats')->first()->formats->first()->is($format));
        $this->assertSame(1, PrintFormat::whereHas('paperSize', fn ($q) => $q->where('width', '5.25'))->count());
        $this->assertSame(1, PaperSize::whereHas('formats', fn ($q) => $q->where('code', 'REL'))->count());
        $this->assertSame(1, PaperSize::withCount('formats')->first()->formats_count);
        $another = PaperSize::create(['name' => 'Otro', 'width' => '10', 'height' => '5']);
        $format->paperSize()->associate($another);
        $format->save();
        $this->assertTrue($format->fresh()->paperSize->is($another));
    }

    public function test_json_casts_bulk_updates_and_update_or_create_with_aliases(): void
    {
        $paper = PaperSize::create(['name' => 'Etiqueta', 'width' => '5.1234', 'height' => '3']);
        $format = PrintFormat::create(['code' => 'JSON', 'name' => 'JSON', 'paper_size_id' => $paper->id]);
        $design = ['schema_version' => 1, 'elements' => [['id' => 'text-1', 'type' => 'text', 'text' => 'Precio', 'x' => '0.0000', 'y' => '0.0000', 'width' => '1.0000', 'height' => '0.5000']]];
        $format->update(['detail' => $design]);
        PrintFormat::whereKey($format->id)->update(['parameters' => ['future' => [1, 2]], 'copies' => 3, 'preview' => false]);
        $this->assertSame($design, $format->fresh()->detail);
        $this->assertSame(3, $format->fresh()->copies);
        $this->assertFalse($format->fresh()->preview);
        $this->assertSame('5.1234', $paper->fresh()->width);
        $this->assertSame(1, PrintFormat::where('detail->schema_version', 1)->count());
        $this->assertSame(1, PrintFormat::whereJsonLength('detail->elements', 1)->count());
        $this->assertSame(1, PrintFormat::whereJsonContains('parameters->future', 1)->count());
        $updated = PrintFormat::updateOrCreate(['code' => 'JSON'], ['copies' => 2]);
        $this->assertSame($format->id, $updated->id);
        $this->assertSame(2, $updated->copies);
        $this->assertTrue($updated->delete());
    }

    public function test_hidden_dirty_original_refresh_and_pagination_remain_canonical(): void
    {
        $field = PrintField::create(['placeholder' => 'CODE', 'display_name' => 'Original', 'associated_field' => 'source']);
        $field->display_name = 'Changed';
        $this->assertTrue($field->isDirty('display_name'));
        $this->assertSame('Original', $field->getOriginal('display_name'));
        $field->save();
        $field->refresh()->makeHidden('display_name');
        $this->assertFalse($field->isDirty());
        $this->assertArrayNotHasKey('display_name', $field->toArray());
        $this->assertArrayNotHasKey('nombre_visible', $field->toArray());
        $page = PrintField::orderBy('id')->paginate(10, ['id', 'display_name']);
        $this->assertSame(1, $page->total());
        $this->assertSame('Changed', $page->first()->display_name);
        $this->assertNotNull($field->updated_at);
    }

    public function test_design_validation_uses_configured_models_aliases_and_global_scopes(): void
    {
        config(['print-kit.models.field' => ScopedField::class, 'print-kit.models.paper_size' => IndependentPaperSize::class, 'print-kit.models.format' => CustomFormat::class]);
        $resolver = $this->app->make(ModelResolver::class);
        $paper = $resolver->make('paper_size')->newQuery()->create(['name' => 'Alias', 'width' => 5, 'height' => 3]);
        $field = $resolver->make('field')->newQuery()->create(['placeholder' => 'DESCRIPTION', 'display_name' => 'Descripción', 'description' => 'allowed', 'associated_field' => 'descripcio', 'usable_in_detail' => true]);
        $format = $resolver->make('format')->newQuery()->create(['code' => 'DESIGN', 'name' => 'Alias', 'paper_size_id' => $paper->id]);
        $design = LabelDesign::fromArray(['schema_version' => 1, 'elements' => [['id' => 'field-1', 'type' => 'field', 'field' => 'DESCRIPTION', 'x' => 0, 'y' => 0, 'width' => 1, 'height' => 1]]]);
        $format->setDesign($design)->save();
        $this->assertInstanceOf(CustomFormat::class, $format);
        $this->assertInstanceOf(IndependentPaperSize::class, $format->paperSize);
        $this->assertSame($design->toArray(), $format->fresh()->detail);
        $this->assertSame($design->toArray(), json_decode(DB::table('custom_formats')->value('detalle'), true));
        $field->update(['description' => 'not_allowed']);
        $this->expectException(InvalidDesign::class);
        $design->validateFor($format);
    }

    public function test_physical_and_qualified_bulk_design_columns_are_protected(): void
    {
        $this->expectException(\LogicException::class);
        PrintFormat::query()->update(['custom_formats.detalle' => '{"schema_version":2}']);
    }
}
