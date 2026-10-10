<?php

declare(strict_types=1);

namespace Ahinest\LaravelPrintKit\Tests\Http;

use Ahinest\LaravelPrintKit\Tests\Fixtures\IndependentField;
use Ahinest\LaravelPrintKit\Tests\Fixtures\IndependentFormat;
use Ahinest\LaravelPrintKit\Tests\Fixtures\IndependentPaperSize;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AliasesApiTest extends ApiTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $maps = ['formats' => ['id' => 'clave', 'code' => 'codigo', 'name' => 'nombre', 'detail' => 'diseno', 'paper_size_id' => 'papel_id', 'system' => 'sistema', 'category' => 'categoria', 'disabled' => 'inactivo'],
            'paper_sizes' => ['id' => 'clave', 'width' => 'ancho', 'height' => 'alto', 'name' => 'nombre', 'is_active' => 'activo'],
            'fields' => ['id' => 'clave', 'placeholder' => 'comodin', 'display_name' => 'nombre', 'associated_field' => 'columna', 'usable_in_detail' => 'utilizable', 'system' => 'sistema', 'category' => 'categoria', 'driver' => 'conexion']];
        foreach ($maps as $entity => $map) {
            $original = config("print-kit.tables.$entity");
            $table = 'custom_'.$entity;
            Schema::rename($original, $table);
            foreach ($map as $canonical => $physical) {
                Schema::table($table, fn ($schema) => $schema->renameColumn($canonical, $physical));
                config(["print-kit.columns.$entity.$canonical" => $physical]);
            }
            config(["print-kit.tables.$entity" => $table]);
        }
        config(['print-kit.models.format' => IndependentFormat::class, 'print-kit.models.paper_size' => IndependentPaperSize::class, 'print-kit.models.field' => IndependentField::class]);
    }

    public function test_full_http_flow_with_independent_models_and_physical_aliases(): void
    {
        $payload = $this->payload(true);
        $created = $this->postJson('/print-kit/formats', $payload)->assertCreated()->assertJsonPath('data.paper_size.width', '5.0000')->assertJsonMissingPath('data.codigo');
        $id = $created->json('data.id');
        $this->assertSame('LABEL', DB::table('custom_formats')->value('codigo'));
        $this->getJson('/print-kit/formats?search=LABEL&system=INV&category=ET&per_page=1')->assertOk()->assertJsonPath('data.0.name', 'Etiqueta')->assertJsonMissingPath('data.0.detail');
        $this->getJson("/print-kit/formats/$id")->assertOk()->assertJsonPath('data.detail.schema_version', 1);
        $this->postJson('/print-kit/formats', $payload)->assertUnprocessable()->assertJsonValidationErrors('code');
        $this->putJson("/print-kit/formats/$id", ['code' => 'LABEL', 'name' => 'Nuevo'])->assertOk()->assertJsonPath('data.name', 'Nuevo');
        $this->getJson('/print-kit/options/paper-sizes')->assertOk()->assertJsonPath('data.0.width', '5.0000');
        $this->getJson('/print-kit/options/fields?driver=mysql&system=INV&category=ET')->assertOk()->assertJsonPath('data.0.value', 'DESCRIPTION')->assertJsonMissingPath('data.0.associated_field');
        $this->postJson('/print-kit/design/validate', $this->onlyDesign($payload))->assertOk();
        $this->postJson('/print-kit/preview', ['format' => $this->onlyDesign($payload), 'values' => ['DESCRIPTION' => 'ñ']])->assertOk()->assertSee('ñ', false);
        $this->postJson("/print-kit/formats/$id/preview", ['values' => ['DESCRIPTION' => 'saved']])->assertOk();
        $this->deleteJson("/print-kit/formats/$id")->assertNoContent();
        $this->assertSame(1, DB::table('custom_paper_sizes')->count());
    }

    private function onlyDesign(array $data): array
    {
        unset($data['code'],$data['name']);

        return $data;
    }

    public function test_catalog_administration_uses_independent_models_aliases_and_storage(): void
    {
        config(['print-kit.database.connection' => 'testing', 'database.connections.empty' => config('database.connections.testing'), 'database.default' => 'empty']);
        DB::connection('empty')->enableQueryLog();
        try {
            $paper = $this->postJson('/print-kit/paper-sizes', ['name' => 'Alias', 'width' => 5, 'height' => 3])->assertCreated()
                ->assertJsonPath('data.width', '5.0000')->assertJsonMissingPath('data.ancho')->json('data.id');
            $this->getJson('/print-kit/paper-sizes?search=Alias&is_active=1')->assertOk()->assertJsonCount(1, 'data');
            $this->getJson("/print-kit/paper-sizes/$paper")->assertOk();
            $this->putJson("/print-kit/paper-sizes/$paper", ['is_active' => false])->assertOk()->assertJsonPath('data.is_active', false);
            $field = ['driver' => 'mysql', 'system' => 'INV', 'category' => '*', 'placeholder' => 'CODE', 'display_name' => 'Código',
                'associated_field' => 'code', 'data_type' => 'D,2', 'usable_in_detail' => true];
            $id = $this->postJson('/print-kit/fields', $field)->assertCreated()->assertJsonMissingPath('data.columna')->json('data.id');
            $this->getJson('/print-kit/fields?driver=mysql&system=INV&category=*&search=code')->assertOk()->assertJsonPath('data.0.associated_field', 'code');
            $this->getJson("/print-kit/fields/$id")->assertOk()->assertJsonPath('data.data_type', 'D,2');
            $this->putJson("/print-kit/fields/$id", ['display_name' => 'Editado'])->assertOk();
            $this->postJson('/print-kit/fields', $field)->assertUnprocessable()->assertJsonValidationErrors('placeholder');
            $this->putJson("/print-kit/fields/$id", ['associated_field' => 'table.column'])->assertUnprocessable()->assertJsonValidationErrors('associated_field');
            $format = $this->postJson('/print-kit/formats', ['code' => 'ALIAS', 'name' => 'Alias', 'paper_size_id' => $paper])->assertCreated()->json('data.id');
            $this->deleteJson("/print-kit/paper-sizes/$paper")->assertStatus(409);
            $this->deleteJson("/print-kit/formats/$format")->assertNoContent();
            $this->deleteJson("/print-kit/paper-sizes/$paper")->assertNoContent();
            $this->assertSame([], DB::connection('empty')->getQueryLog());
        } finally {
            config(['database.default' => 'testing']);
        }
    }

    public function test_domain_validation_still_runs_for_independent_format(): void
    {
        $data = $this->payload();
        $data['detail']['elements'][0]['x'] = 6;
        $this->postJson('/print-kit/formats', $data)->assertUnprocessable()->assertJsonValidationErrors('detail.elements.0.x');
        $this->assertSame(0, DB::table('custom_formats')->count());
    }

    public function test_partial_update_rejects_empty_required_strings_for_independent_model(): void
    {
        $id = $this->postJson('/print-kit/formats', $this->payload())->assertCreated()->json('data.id');
        foreach (['code', 'name', 'copies', 'preview', 'paper_size_id', 'orientation', 'system', 'category', 'use_default_printer', 'disabled'] as $key) {
            $this->putJson("/print-kit/formats/$id", [$key => ''])->assertUnprocessable()->assertJsonValidationErrors($key);
        }
    }
}
