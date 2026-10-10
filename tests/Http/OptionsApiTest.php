<?php

declare(strict_types=1);

namespace Ahinest\LaravelPrintKit\Tests\Http;

use Ahinest\LaravelPrintKit\Models\PaperSize;
use Ahinest\LaravelPrintKit\Models\PrintField;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;

class OptionsApiTest extends ApiTestCase
{
    public function test_registries_are_sorted_safe_and_query_free(): void
    {
        config(['print-kit.drivers' => ['z' => ['label' => 'Zulu', 'description' => 'z'], 'a' => ['label' => 'Alfa', 'description' => 'a']]]);
        $this->watch();
        $this->getJson('/print-kit/options/drivers')->assertOk()->assertExactJson(['data' => [['value' => 'a', 'label' => 'Alfa', 'description' => 'a'], ['value' => 'z', 'label' => 'Zulu', 'description' => 'z']]]);
        $this->getJson('/print-kit/options/systems')->assertOk()->assertJsonPath('data.0.categories', ['ET'])->assertJsonMissingPath('data.0.table')->assertJsonMissingPath('data.0.key');
        $this->getJson('/print-kit/options/categories')->assertOk()->assertJsonPath('data.0.value', 'ET');
        $this->assertCount(0, DB::getQueryLog());
    }

    public function test_papers_are_active_by_default_sorted_and_one_query(): void
    {
        foreach ([['Zeta', true], ['Alfa', true], ['Beta', false]] as [$name,$is_active]) {
            PaperSize::create(compact('name', 'is_active') + ['width' => 5, 'height' => 3]);
        }
        $this->watch();
        $this->getJson('/print-kit/options/paper-sizes')->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('data.0.label', 'Alfa')->assertJsonPath('data.0.width', '5.0000');
        $this->assertCount(1, DB::getQueryLog());
        $this->getJson('/print-kit/options/paper-sizes?include_inactive=1')->assertOk()->assertJsonCount(3, 'data');
        $this->getJson('/print-kit/options/paper-sizes?include_inactive=bad')->assertUnprocessable()->assertJsonValidationErrors('include_inactive');
    }

    public function test_fields_precedence_permission_order_and_projection(): void
    {
        foreach ([['A', '*', true, 'Zeta'], ['A', 'ET', true, 'Alfa'], ['B', '*', true, 'Beta'], ['B', 'ET', false, 'Beta'], ['C', '*', true, 'Alfa']] as [$placeholder,$category,$usable_in_detail,$display_name]) {
            PrintField::create(compact('placeholder', 'category', 'usable_in_detail', 'display_name') + ['associated_field' => 'private_column']);
        }
        $this->watch();
        $response = $this->getJson('/print-kit/options/fields?driver=mysql&system=INV&category=ET')->assertOk()->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.value', 'A')->assertJsonPath('data.1.value', 'C')->assertJsonPath('data.0.data_type', 'C');
        $this->assertSame(['value', 'label', 'description', 'data_type'], array_keys($response->json('data.0')));
        $this->assertCount(1, DB::getQueryLog());
    }

    #[DataProvider('invalidFieldFilters')]
    public function test_invalid_field_filters(string $query, string $field): void
    {
        $this->getJson('/print-kit/options/fields?'.$query)->assertUnprocessable()->assertJsonValidationErrors($field);
    }

    public static function invalidFieldFilters(): array
    {
        return [['system=INV&category=ET', 'driver'], ['driver=unknown&system=INV&category=ET', 'driver'], ['driver=mysql&category=ET', 'system'],
            ['driver=mysql&system=XXX&category=ET', 'system'], ['driver=mysql&system=INV', 'category'], ['driver=mysql&system=INV&category=*', 'category']];
    }
}
