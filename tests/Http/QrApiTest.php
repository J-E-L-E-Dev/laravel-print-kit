<?php

declare(strict_types=1);

namespace Ahinest\LaravelPrintKit\Tests\Http;

use Ahinest\LaravelPrintKit\Data\LabelResolver;
use Ahinest\LaravelPrintKit\Models\PrintField;
use Ahinest\LaravelPrintKit\Models\PrintFormat;
use Ahinest\LaravelPrintKit\Rendering\LabelRenderService;
use Ahinest\LaravelPrintKit\Rendering\QrCodeGenerator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class QrApiTest extends ApiTestCase
{
    private function qrDraft(): array
    {
        $draft = $this->draft();
        $draft['detail']['elements'][] = array_replace($draft['detail']['elements'][0], ['id' => 'qr', 'render_as' => 'qr', 'y' => 1, 'height' => 1.5]);

        return $draft;
    }

    public function test_preview_and_stored_preview_share_value_and_safe_errors(): void
    {
        $draft = $this->qrDraft();
        config(['database.connections.mysql' => ['driver' => 'do-not-open']]);
        $payload = ['format' => $draft, 'values' => ['DESCRIPTION' => '001-012']];
        $this->postJson('/print-kit/preview', $payload, ['Accept' => 'text/html'])->assertOk()->assertSee('001-012')->assertSee('data:image/svg+xml;base64,', false);
        $id = $this->postJson('/print-kit/formats', ['code' => 'QR', 'name' => 'QR'] + $draft)->assertCreated()->json('data.id');
        $this->getJson('/print-kit/formats/'.$id)->assertOk()->assertJsonPath('data.detail.elements.1.render_as', 'qr');
        $this->postJson('/print-kit/formats/'.$id.'/preview', ['values' => $payload['values']], ['Accept' => 'text/html'])->assertOk()->assertSee('data:image/svg+xml;base64,', false);
        $this->assertArrayNotHasKey('mysql', DB::getConnections());
        $huge = str_repeat('private-content-', 1000);
        $this->postJson('/print-kit/preview', ['format' => $draft, 'values' => ['DESCRIPTION' => $huge]], ['Accept' => 'text/html'])
            ->assertUnprocessable()->assertJsonValidationErrors('format.fields')->assertDontSee('private-content')->assertDontSee('BaconQrCode');
        $this->postJson('/print-kit/formats/'.$id.'/preview', ['values' => ['DESCRIPTION' => $huge]], ['Accept' => 'text/html'])
            ->assertUnprocessable()->assertJsonValidationErrors('format.fields');
    }

    public function test_database_final_value_resolves_once_and_qr_render_adds_no_queries(): void
    {
        $draft = $this->qrDraft();
        $field = PrintField::first();
        $field->update(['data_type' => 'D,2', 'associated_field' => 'amount']);
        config(['database.connections.mysql' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''],
            'print-kit.systems.INV.table' => 'qr_products', 'print-kit.systems.INV.key' => 'sku']);
        Schema::connection('mysql')->create('qr_products', function ($table) {
            $table->string('sku');
            $table->string('amount');
        });
        DB::connection('mysql')->table('qr_products')->insert(['sku' => '001-012', 'amount' => '125.5']);
        $format = PrintFormat::create(['code' => 'QRDB', 'name' => 'Real'] + $draft);
        $format->load('paperSize');
        $this->watch();
        DB::connection('mysql')->enableQueryLog();
        $labels = app(LabelResolver::class)->resolveByKeys($format, 'mysql', ['001-012']);
        $this->assertCount(1, DB::getQueryLog()); // Un catálogo para las dos apariciones.
        $this->assertCount(1, DB::connection('mysql')->getQueryLog());
        $generator = new class extends QrCodeGenerator
        {
            public array $seen = [];

            public function generateDataUri(string $value, string $elementId = '', string $placeholder = ''): string
            {
                $this->seen[] = $value;

                return parent::generateDataUri($value, $elementId, $placeholder);
            }
        };
        $this->app->instance(QrCodeGenerator::class, $generator);
        $this->watch();
        DB::connection('mysql')->flushQueryLog();
        $document = app(LabelRenderService::class)->renderCollection($format, $labels);
        $this->assertSame(['125.50'], $generator->seen);
        $this->assertSame([], DB::getQueryLog());
        $this->assertSame([], DB::connection('mysql')->getQueryLog());
        $this->assertStringContainsString('125.50', $document->html());
        $this->assertStringContainsString('data:image/svg+xml;base64,', $document->html());
    }
}
