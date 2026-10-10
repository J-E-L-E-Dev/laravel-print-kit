<?php

declare(strict_types=1);

namespace Ahinest\LaravelPrintKit\Tests\Http;

use Ahinest\LaravelPrintKit\Http\FormatManager;
use Ahinest\LaravelPrintKit\Models\PaperSize;
use Ahinest\LaravelPrintKit\Models\PrintFormat;
use Illuminate\Support\Facades\DB;

class StorageApiTest extends ApiTestCase
{
    public function test_format_scopes_limit_reads_and_mutations_but_unique_code_remains_global(): void
    {
        $format = PrintFormat::create($this->payload());
        PrintFormat::addGlobalScope('hidden', fn ($query) => $query->where('disabled', true));
        $this->getJson('/print-kit/formats')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson("/print-kit/formats/{$format->id}")->assertNotFound();
        $this->putJson("/print-kit/formats/{$format->id}", ['disabled' => true])->assertNotFound();
        $this->deleteJson("/print-kit/formats/{$format->id}")->assertNotFound();
        $payload = $this->payload();
        $this->postJson('/print-kit/formats', $payload)->assertUnprocessable()->assertJsonValidationErrors('code');
    }

    public function test_all_http_storage_uses_configured_connection(): void
    {
        $payload = $this->payload(true);
        config(['print-kit.database.connection' => 'testing', 'database.connections.empty' => config('database.connections.testing'), 'database.default' => 'empty']);
        DB::connection('empty')->enableQueryLog();
        try {
            $id = $this->postJson('/print-kit/formats', $payload)->assertCreated()->json('data.id');
            $this->getJson('/print-kit/formats?search=LABEL')->assertOk()->assertJsonCount(1, 'data');
            $this->getJson("/print-kit/formats/$id")->assertOk();
            $this->getJson('/print-kit/options/paper-sizes')->assertOk()->assertJsonCount(1, 'data');
            $this->getJson('/print-kit/options/fields?driver=mysql&system=INV&category=ET')->assertOk()->assertJsonCount(1, 'data');
            $this->postJson("/print-kit/formats/$id/preview", ['values' => ['DESCRIPTION' => 'stored']])->assertOk();
            $this->postJson('/print-kit/preview', ['format' => $payload, 'values' => ['DESCRIPTION' => 'draft']])->assertOk();
            $this->putJson("/print-kit/formats/$id", ['name' => 'Edited'])->assertOk();
            $this->deleteJson("/print-kit/formats/$id")->assertNoContent();
            $this->assertSame([], DB::connection('empty')->getQueryLog());
        } finally {
            config(['database.default' => 'testing']);
        }
    }

    public function test_shared_preview_reuses_loaded_paper_and_update_invalidates_changed_relation(): void
    {
        $format = PrintFormat::create($this->payload(true));
        $format->load('paperSize');
        $manager = app(FormatManager::class);
        $this->watch();
        $html = $manager->preview($format, ['DESCRIPTION' => 'preloaded'])->html();
        $this->assertStringContainsString('preloaded', $html);
        $this->assertCount(1, DB::getQueryLog());
        $this->assertStringContainsString('print_fields', DB::getQueryLog()[0]['query']);
        $other = PaperSize::create(['name' => 'Inactive paper', 'width' => 6, 'height' => 4, 'is_active' => false]);
        $updated = $manager->update($format, ['paper_size_id' => $other->id]);
        $this->assertSame($other->id, $manager->paper($updated)->id);
        $this->getJson("/print-kit/formats/{$format->id}")->assertOk()->assertJsonPath('data.paper_size.width', '6.0000');
    }

    public function test_configured_pagination_and_summary_do_not_load_details_or_papers(): void
    {
        $payload = $this->payload();
        foreach (['A', 'B', 'C'] as $code) {
            PrintFormat::create(array_replace($payload, ['code' => $code]));
        }
        config(['print-kit.api.per_page' => 10, 'print-kit.api.max_per_page' => 2]);
        $this->watch();
        $this->getJson('/print-kit/formats')->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('meta.per_page', 2);
        $queries = DB::getQueryLog();
        $this->assertCount(2, $queries);
        $this->assertStringNotContainsString('"detail"', $queries[1]['query']);
        $this->assertStringNotContainsString('print_paper_sizes', $queries[1]['query']);
        $this->getJson('/print-kit/formats?per_page=3')->assertUnprocessable();
    }
}
