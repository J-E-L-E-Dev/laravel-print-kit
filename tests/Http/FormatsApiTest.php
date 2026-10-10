<?php

declare(strict_types=1);

namespace Ahinest\LaravelPrintKit\Tests\Http;

use Ahinest\LaravelPrintKit\Models\PaperSize;
use Ahinest\LaravelPrintKit\Models\PrintFormat;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;

class FormatsApiTest extends ApiTestCase
{
    public function test_crud_with_details_disabled_parameters_and_versions(): void
    {
        $payload = $this->payload() + ['copies' => 5, 'preview' => false, 'printer' => 'Local', 'use_default_printer' => true, 'disabled' => true, 'parameters' => ['custom' => ['x' => 1]]];
        $created = $this->postJson('/print-kit/formats', $payload)->assertCreated()->assertJsonPath('data.version', 1)->assertJsonPath('data.detail.schema_version', 1)
            ->assertJsonPath('data.paper_size.width', '5.0000')->assertJsonPath('data.disabled', true)->assertJsonPath('data.parameters.custom.x', 1);
        $id = $created->json('data.id');
        $this->getJson("/print-kit/formats/$id")->assertOk()->assertJsonPath('data.code', 'LABEL')->assertJsonPath('data.designer.font_size_unit', 'pt');
        $this->getJson('/print-kit/formats')->assertOk()->assertJsonCount(1, 'data')->assertJsonMissingPath('data.0.detail')->assertJsonPath('data.0.copies', 5);
        $this->putJson("/print-kit/formats/$id", ['name' => 'Nuevo', 'code' => 'LABEL', 'disabled' => false, 'parameters' => null, 'printer' => null])->assertOk()
            ->assertJsonPath('data.name', 'Nuevo')->assertJsonPath('data.parameters', null)->assertJsonPath('data.printer', null)->assertJsonPath('data.disabled', false);
        $this->deleteJson("/print-kit/formats/$id")->assertNoContent();
        $this->assertSame(0, PrintFormat::count());
        $this->assertSame(1, PaperSize::count());
        $this->getJson("/print-kit/formats/$id")->assertNotFound();
    }

    public function test_filters_search_and_pagination(): void
    {
        $data = $this->payload();
        foreach ([['A', 'Alpha', false], ['B', 'Beta', true], ['C', 'Gamma', false]] as [$code,$name,$disabled]) {
            $this->postJson('/print-kit/formats', array_replace($data, compact('code', 'name', 'disabled')))->assertCreated();
        }
        $page = $this->getJson('/print-kit/formats?per_page=1&page=2')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('meta.total', 3)->assertJsonPath('meta.current_page', 2);
        $this->assertSame('B', $page->json('data.0.code'));
        $this->getJson('/print-kit/formats?search=Beta&system=INV&category=ET&disabled=1')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.code', 'B');
        $this->getJson('/print-kit/formats?disabled=0')->assertOk()->assertJsonCount(2, 'data');
        $this->getJson('/print-kit/formats?search=%27%20OR%201%3D1')->assertOk()->assertJsonCount(0, 'data');
    }

    #[DataProvider('badFilters')]
    public function test_invalid_filters(string $query, string $key): void
    {
        $this->getJson('/print-kit/formats?'.$query)->assertUnprocessable()->assertJsonValidationErrors($key);
    }

    public static function badFilters(): array
    {
        return [['per_page=101', 'per_page'], ['per_page=0', 'per_page'], ['page=0', 'page'], ['disabled=maybe', 'disabled'], ['system=XXX', 'system'], ['category=RP', 'category']];
    }

    public function test_code_uniqueness_create_update_and_ignoring_self(): void
    {
        $data = $this->payload();
        $id = $this->postJson('/print-kit/formats', $data)->assertCreated()->json('data.id');
        $this->postJson('/print-kit/formats', $data)->assertUnprocessable()->assertJsonValidationErrors('code');
        $id2 = $this->postJson('/print-kit/formats', array_replace($data, ['code' => 'OTHER']))->assertCreated()->json('data.id');
        $this->putJson("/print-kit/formats/$id2", ['code' => 'LABEL'])->assertUnprocessable()->assertJsonValidationErrors('code');
        $this->putJson("/print-kit/formats/$id", ['code' => 'LABEL'])->assertOk();
    }

    #[DataProvider('badAttributes')]
    public function test_invalid_format_input(array $changes, string $error): void
    {
        $this->postJson('/print-kit/formats', array_replace($this->payload(), $changes))->assertUnprocessable()->assertJsonValidationErrors($error);
        $this->assertSame(0, PrintFormat::count());
    }

    public static function badAttributes(): array
    {
        return [[['code' => ''], 'code'], [['name' => ''], 'name'], [['copies' => 0], 'copies'], [['copies' => 1.5], 'copies'], [['orientation' => 'X'], 'orientation'],
            [['system' => 'XXX'], 'system'], [['category' => 'RP'], 'category'], [['category' => '*'], 'category'],
            [['margins' => ['top' => 0, 'right' => 0, 'bottom' => 0, 'left' => 6]], 'margins'],
            [['parameters' => 'text'], 'parameters'], [['detail' => ['schema_version' => 2, 'elements' => []]], 'detail.schema_version'],
            [['id' => 99], 'id'], [['version' => 7], 'version']];
    }

    public function test_paper_and_format_not_found_are_404(): void
    {
        $payload = $this->payload();
        $payload['paper_size_id'] = 999;
        $this->postJson('/print-kit/formats', $payload)->assertNotFound();
        $this->putJson('/print-kit/formats/999', ['name' => 'x'])->assertNotFound();
        $this->deleteJson('/print-kit/formats/999')->assertNotFound();
    }

    public function test_update_invalid_geometry_does_not_persist_changes(): void
    {
        $data = $this->payload();
        $id = $this->postJson('/print-kit/formats', $data)->assertCreated()->json('data.id');
        $this->putJson("/print-kit/formats/$id", ['name' => 'changed', 'margins' => ['top' => 0, 'right' => 0, 'bottom' => 0, 'left' => 1]])->assertUnprocessable();
        $this->assertSame('Etiqueta', PrintFormat::findOrFail($id)->name);
    }

    public function test_show_queries_format_and_paper_only(): void
    {
        $id = $this->postJson('/print-kit/formats', $this->payload())->assertCreated()->json('data.id');
        $this->watch();
        $this->getJson("/print-kit/formats/$id")->assertOk();
        $this->assertCount(2, DB::getQueryLog());
    }

    public function test_runtime_storage_error_does_not_expose_debug_details(): void
    {
        config(['app.debug' => true, 'print-kit.tables.formats' => 'missing_private_table']);
        $response = $this->getJson('/print-kit/formats')->assertStatus(500)->assertExactJson(['message' => 'No se pudo completar la operación.']);
        $this->assertStringNotContainsString('missing_private_table', $response->getContent());
    }

    public function test_json_body_is_required_and_invalid_json_has_useful_error(): void
    {
        $this->post('/print-kit/formats', ['code' => 'x'])->assertStatus(415);
        $this->call('POST', '/print-kit/formats', [], [], [], ['CONTENT_TYPE' => 'application/json'], '{')->assertStatus(422)->assertJsonValidationErrors('body');
    }
}
