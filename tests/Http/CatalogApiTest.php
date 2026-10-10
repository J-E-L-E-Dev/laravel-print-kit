<?php

declare(strict_types=1);

namespace Ahinest\LaravelPrintKit\Tests\Http;

use Ahinest\LaravelPrintKit\Models\PaperSize;
use Ahinest\LaravelPrintKit\Models\PrintField;
use Ahinest\LaravelPrintKit\Models\PrintFormat;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;

class CatalogApiTest extends ApiTestCase
{
    private function field(array $changes = []): array
    {
        return array_replace(['driver' => 'mysql', 'system' => 'INV', 'category' => 'ET',
            'placeholder' => 'DESCRIPTION', 'display_name' => 'Descripción', 'description' => null,
            'data_type' => 'C', 'usable_in_detail' => true, 'associated_field' => 'descripcio'], $changes);
    }

    public function test_paper_crud_decimal_filters_pagination_and_options(): void
    {
        $id = $this->postJson('/print-kit/paper-sizes', ['name' => 'Etiqueta', 'description' => 'Térmica', 'width' => 5, 'height' => '3.25'])
            ->assertCreated()->assertJsonPath('data.width', '5.0000')->assertJsonPath('data.height', '3.2500')
            ->assertJsonPath('data.is_active', true)->json('data.id');
        $this->getJson("/print-kit/paper-sizes/$id")->assertOk()->assertJsonPath('data.name', 'Etiqueta');
        $this->putJson("/print-kit/paper-sizes/$id", ['is_active' => false, 'description' => null])->assertOk()->assertJsonPath('data.description', null);
        $this->getJson('/print-kit/options/paper-sizes')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/print-kit/options/paper-sizes?include_inactive=1')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/print-kit/paper-sizes?search=Etiqueta&is_active=0')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/print-kit/paper-sizes?is_active=1')->assertOk()->assertJsonCount(0, 'data');
        $this->putJson("/print-kit/paper-sizes/$id", ['is_active' => true, 'name' => 'A'])->assertOk();
        $this->postJson('/print-kit/paper-sizes', ['name' => 'B', 'description' => 'Especial', 'width' => '0.0001', 'height' => '999999.9999'])->assertCreated();
        $this->getJson('/print-kit/paper-sizes?search=Especial')->assertOk()->assertJsonPath('data.0.name', 'B');
        config(['print-kit.api.per_page' => 5, 'print-kit.api.max_per_page' => 1]);
        $this->getJson('/print-kit/paper-sizes?page=2')->assertOk()->assertJsonPath('data.0.name', 'B')->assertJsonPath('meta.total', 2)->assertJsonPath('meta.per_page', 1);
        $this->getJson('/print-kit/paper-sizes?per_page=2')->assertUnprocessable();
        $this->deleteJson("/print-kit/paper-sizes/$id")->assertNoContent();
        $this->getJson("/print-kit/paper-sizes/$id")->assertNotFound();
        $this->putJson("/print-kit/paper-sizes/$id", ['name' => 'A'])->assertNotFound();
        $this->deleteJson("/print-kit/paper-sizes/$id")->assertNotFound();
    }

    public function test_referenced_paper_cannot_be_deleted_even_if_format_is_scoped_out(): void
    {
        $format = PrintFormat::create($this->payload());
        PrintFormat::addGlobalScope('hidden', fn ($query) => $query->where('disabled', true));
        $this->deleteJson('/print-kit/paper-sizes/'.$format->paper_size_id)->assertStatus(409)
            ->assertJsonPath('message', 'El tamaño de papel está siendo utilizado por uno o más formatos y no puede eliminarse.');
        $this->assertSame(1, PaperSize::count());
        $this->putJson('/print-kit/paper-sizes/'.$format->paper_size_id, ['is_active' => false])->assertOk();
        $this->assertSame('5.0000', $format->fresh()->paperSize->width);
    }

    public static function invalidMeasures(): array
    {
        return [[0], [-1], ['1000000'], ['1.00001'], [null], ['bad'], [[]]];
    }

    #[DataProvider('invalidMeasures')]
    public function test_invalid_dimensions_are_rejected(mixed $value): void
    {
        foreach (['width', 'height'] as $key) {
            $this->postJson('/print-kit/paper-sizes', array_replace(['name' => 'Invalid', 'width' => 5, 'height' => 3], [$key => $value]))
                ->assertUnprocessable()->assertJsonValidationErrors($key);
        }
        $this->assertSame(0, PaperSize::count());
    }

    public static function dataTypes(): array
    {
        return [['C'], ['N'], ['D'], ['D,0'], ['D,2'], ['D,18'], ['T']];
    }

    #[DataProvider('dataTypes')]
    public function test_field_crud_preserves_data_type_and_ignores_own_identity(string $type): void
    {
        $id = $this->postJson('/print-kit/fields', $this->field(['data_type' => $type]))->assertCreated()->assertJsonPath('data.data_type', $type)->json('data.id');
        $this->getJson("/print-kit/fields/$id")->assertOk()->assertJsonPath('data.associated_field', 'descripcio');
        $this->putJson("/print-kit/fields/$id", $this->field(['display_name' => 'Editado', 'data_type' => $type, 'usable_in_detail' => false]))
            ->assertOk()->assertJsonPath('data.display_name', 'Editado')->assertJsonPath('data.usable_in_detail', false);
        $this->postJson('/print-kit/fields', $this->field())->assertUnprocessable()->assertJsonValidationErrors('placeholder');
        $this->deleteJson("/print-kit/fields/$id")->assertStatus(405);
        $this->getJson('/print-kit/fields/9999')->assertNotFound();
        $this->putJson('/print-kit/fields/9999', ['description' => 'X'])->assertNotFound();
    }

    public static function invalidFields(): array
    {
        return [
            ['driver', 'missing'], ['system', 'BAD'], ['category', 'NO'], ['category', ''],
            ['placeholder', 'Bad value'], ['placeholder', ' PADDED '], ['placeholder', str_repeat('A', 101)],
            ['display_name', ''], ['display_name', null], ['data_type', 'D,19'], ['data_type', 'D,-1'],
            ['associated_field', 'table.column'], ['associated_field', 'SUM(price)'], ['associated_field', 'SELECT * FROM products'],
            ['associated_field', 'price AS value'], ['usable_in_detail', 'yes'],
        ];
    }

    #[DataProvider('invalidFields')]
    public function test_invalid_field_attributes_have_useful_errors(string $key, mixed $value): void
    {
        $this->postJson('/print-kit/fields', $this->field([$key => $value]))->assertUnprocessable()->assertJsonValidationErrors($key);
        $this->assertSame(0, PrintField::count());
    }

    public function test_category_membership_and_literal_placeholder_case(): void
    {
        config(['print-kit.categories.XX' => ['label' => 'Otra', 'description' => 'Otra categoría']]);
        $this->postJson('/print-kit/fields', $this->field(['category' => 'XX']))->assertUnprocessable()->assertJsonValidationErrors('category');
        $this->postJson('/print-kit/fields', $this->field(['placeholder' => 'lowerCase']))->assertCreated()->assertJsonPath('data.placeholder', 'lowerCase');
        $this->postJson('/print-kit/fields', $this->field(['placeholder' => 'LOWERCASE']))->assertCreated()->assertJsonPath('data.placeholder', 'LOWERCASE');
    }

    public function test_field_filters_wildcard_precedence_and_safe_options(): void
    {
        $this->postJson('/print-kit/fields', $this->field(['category' => '*', 'description' => 'Global']))->assertCreated();
        $id = $this->postJson('/print-kit/fields', $this->field(['usable_in_detail' => false]))->assertCreated()->json('data.id');
        $options = '/print-kit/options/fields?driver=mysql&system=INV&category=ET';
        $this->getJson($options)->assertOk()->assertJsonCount(0, 'data');
        $this->putJson("/print-kit/fields/$id", ['usable_in_detail' => true])->assertOk();
        $this->getJson($options)->assertOk()->assertJsonCount(1, 'data')->assertJsonMissingPath('data.0.associated_field')
            ->assertExactJson(['data' => [['value' => 'DESCRIPTION', 'label' => 'Descripción', 'description' => null, 'data_type' => 'C']]]);
        foreach (['DESCRIPTION', 'Descripción', 'descripcio', 'Global'] as $search) {
            $this->getJson('/print-kit/fields?'.http_build_query(['search' => $search]))->assertOk()->assertJsonPath('meta.total', $search === 'Global' ? 1 : 2);
        }
        $this->getJson('/print-kit/fields?driver=mysql&system=INV&category=*&usable_in_detail=1&per_page=1')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.category', '*');
        $this->getJson('/print-kit/fields?usable_in_detail=0')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/print-kit/fields?per_page=1&page=2')->assertOk()->assertJsonPath('meta.total', 2)->assertJsonPath('data.0.category', 'ET');
        $this->getJson('/print-kit/fields?search='.urlencode("' OR 1=1 --"))->assertOk()->assertJsonCount(0, 'data');
        $this->putJson("/print-kit/fields/$id", ['category' => '*'])->assertUnprocessable()->assertJsonValidationErrors('placeholder');
    }

    public function test_admin_does_not_open_source_connection_and_scopes_still_apply(): void
    {
        config(['print-kit.drivers.sqlsrv' => ['label' => 'SQL Server', 'description' => 'Origen no disponible'],
            'database.connections.sqlsrv' => ['driver' => 'unsupported-source-driver']]);
        $id = $this->postJson('/print-kit/fields', $this->field(['driver' => 'sqlsrv']))->assertCreated()->json('data.id');
        $this->putJson("/print-kit/fields/$id", ['description' => 'Sin origen'])->assertOk();
        $this->assertArrayNotHasKey('sqlsrv', DB::getConnections());
        PrintField::addGlobalScope('hidden', fn ($q) => $q->where('usable_in_detail', false));
        $this->getJson('/print-kit/fields')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson("/print-kit/fields/$id")->assertNotFound();
        $this->putJson("/print-kit/fields/$id", ['description' => null])->assertNotFound();
        $this->postJson('/print-kit/fields', $this->field(['driver' => 'sqlsrv']))->assertUnprocessable()->assertJsonValidationErrors('placeholder');
    }

    public function test_http_body_contract_and_paper_scopes(): void
    {
        $this->post('/print-kit/paper-sizes', ['name' => 'X'])->assertStatus(415);
        $this->postJson('/print-kit/paper-sizes', ['name' => 'X', 'width' => 5, 'height' => 3, 'id' => 9])->assertUnprocessable()->assertJsonValidationErrors('id');
        $paper = PaperSize::create(['name' => 'Hidden', 'width' => 5, 'height' => 3]);
        PaperSize::addGlobalScope('hidden', fn ($q) => $q->where('is_active', false));
        $this->getJson('/print-kit/paper-sizes')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson("/print-kit/paper-sizes/{$paper->id}")->assertNotFound();
        $this->putJson("/print-kit/paper-sizes/{$paper->id}", ['name' => 'Hidden'])->assertNotFound();
        $this->deleteJson("/print-kit/paper-sizes/{$paper->id}")->assertNotFound();
    }
}
