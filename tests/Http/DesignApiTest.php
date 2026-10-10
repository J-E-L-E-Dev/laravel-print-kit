<?php

declare(strict_types=1);

namespace Ahinest\LaravelPrintKit\Tests\Http;

use Ahinest\LaravelPrintKit\Data\DatabaseDataResolver;
use Ahinest\LaravelPrintKit\Data\DataTypeFormatter;
use Ahinest\LaravelPrintKit\Data\FieldTransformerRegistry;
use Ahinest\LaravelPrintKit\Data\ProvidedRecordResolver;
use Ahinest\LaravelPrintKit\Models\PrintField;
use Ahinest\LaravelPrintKit\Models\PrintFormat;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;

class DesignApiTest extends ApiTestCase
{
    private function denySources(): void
    {
        foreach ([DatabaseDataResolver::class, FieldTransformerRegistry::class, DataTypeFormatter::class, ProvidedRecordResolver::class] as $class) {
            unset($this->app[$class]);
            $this->app->bind($class, fn () => throw new \LogicException('Acceso prohibido al origen o a transformaciones.'));
        }
    }

    public function test_unsaved_validation_and_preview_use_one_paper_query_and_no_writes(): void
    {
        $format = $this->draft();
        $this->denySources();
        $this->watch();
        $this->postJson('/print-kit/design/validate', $format)->assertOk()->assertExactJson(['valid' => true]);
        $this->assertCount(2, DB::getQueryLog());
        $this->watch();
        $response = $this->postJson('/print-kit/preview', ['format' => $format + ['copies' => 5], 'values' => ['DESCRIPTION' => '<script>alert("x")</script> ñ €']])->assertOk();
        $this->assertSame('text/html; charset=UTF-8', $response->headers->get('Content-Type'));
        $this->assertStringStartsWith('<!DOCTYPE html>', $response->getContent());
        $this->assertStringContainsString('&lt;script&gt;alert(&quot;x&quot;)&lt;/script&gt; ñ €', $response->getContent());
        $this->assertStringNotContainsString('<script>', $response->getContent());
        $this->assertSame(1, substr_count($response->getContent(), 'class="print-kit-label"'));
        $queries = DB::getQueryLog();
        $this->assertCount(2, $queries);
        $this->assertSame(1, count(array_filter($queries, fn ($q) => str_contains($q['query'], 'print_paper_sizes'))));
        $this->assertSame(1, count(array_filter($queries, fn ($q) => str_contains($q['query'], 'print_fields'))));
        $this->assertSame(0, PrintFormat::count());
        $this->assertFalse($response->headers->has('Content-Disposition'));
        $this->assertFalse($response->headers->has('Content-Security-Policy'));
    }

    public function test_literal_text_preview_no_fields_and_horizontal_size(): void
    {
        $format = $this->draft(false);
        $format['orientation'] = 'H';
        $this->watch();
        $response = $this->postJson('/print-kit/preview', ['format' => $format, 'values' => []])->assertOk();
        $this->assertCount(1, DB::getQueryLog());
        $this->assertStringContainsString('@page{size:3.0000cm 5.0000cm;margin:0;}', $response->getContent());
        $this->assertStringContainsString('&lt;b&gt;ñ&lt;/b&gt;', $response->getContent());
        $this->assertStringContainsString('left:0.2000cm;', $response->getContent());
    }

    public function test_missing_preview_value_has_placeholder_path_even_with_accept_html(): void
    {
        $format = $this->draft();
        $this->postJson('/print-kit/preview', ['format' => $format, 'values' => []], ['Accept' => 'text/html'])->assertUnprocessable()
            ->assertHeader('Content-Type', 'application/json')->assertJsonValidationErrors('values.DESCRIPTION')->assertJsonMissingPath('trace');
    }

    #[DataProvider('invalidDesigns')]
    public function test_invalid_design_paths(array $change, string $path): void
    {
        $format = $this->draft();
        $format['detail']['elements'][0] = array_replace($format['detail']['elements'][0], $change);
        $this->postJson('/print-kit/design/validate', $format)->assertUnprocessable()->assertJsonValidationErrors($path);
        $this->postJson('/print-kit/preview', ['format' => $format, 'values' => ['DESCRIPTION' => 'x']])->assertUnprocessable()->assertJsonValidationErrors('format.'.$path);
    }

    public static function invalidDesigns(): array
    {
        return [[['x' => 6], 'detail.elements.0.x'], [['y' => 6], 'detail.elements.0.y'], [['style' => ['color' => 'red']], 'detail.elements.0.style.color'],
            [['style' => ['font_size' => '10;display:none']], 'detail.elements.0.style.font_size'], [['field' => 'MISSING'], 'detail.elements.0.field']];
    }

    public function test_unusable_field_and_invalid_schema(): void
    {
        $format = $this->draft();
        PrintField::query()->update(['usable_in_detail' => false]);
        $this->postJson('/print-kit/design/validate', $format)->assertUnprocessable()->assertJsonValidationErrors('detail.elements.0.field');
        $format['detail']['schema_version'] = 2;
        $this->postJson('/print-kit/design/validate', $format)->assertUnprocessable()->assertJsonValidationErrors('detail.schema_version');
    }

    public function test_validation_missing_paper_returns_404(): void
    {
        $format = $this->draft(false);
        $format['paper_size_id'] = 999;
        $this->postJson('/print-kit/design/validate', $format)->assertNotFound();
    }

    public function test_stored_preview_does_not_change_format_or_repeat_copies(): void
    {
        $data = $this->payload(true) + ['copies' => 5, 'disabled' => true];
        $id = $this->postJson('/print-kit/formats', $data)->assertCreated()->json('data.id');
        $before = PrintFormat::findOrFail($id)->getAttributes();
        $this->denySources();
        $this->watch();
        $response = $this->postJson("/print-kit/formats/$id/preview", ['values' => ['DESCRIPTION' => 'Final,50']])->assertOk();
        $this->assertCount(3, DB::getQueryLog());
        $this->assertSame(1, substr_count($response->getContent(), 'class="print-kit-label"'));
        $this->assertSame($before, PrintFormat::findOrFail($id)->getAttributes());
        $this->postJson("/print-kit/formats/$id/preview", ['values' => []])->assertUnprocessable()->assertJsonValidationErrors('values.DESCRIPTION');
        $this->postJson('/print-kit/formats/999/preview', ['values' => []])->assertNotFound();
    }

    #[DataProvider('finalValues')]
    public function test_json_final_values_survive_global_trim_and_empty_string_middleware(mixed $value, string $expected): void
    {
        $format = $this->draft();
        $response = $this->postJson('/print-kit/preview', ['format' => $format, 'values' => ['DESCRIPTION' => $value]])->assertOk();
        $document = new \DOMDocument;
        $document->loadHTML($response->getContent());
        $node = (new \DOMXPath($document))->query('//div[@data-element-id="a"]')->item(0);
        $this->assertSame($expected, $node->textContent);
    }

    public static function finalValues(): array
    {
        return [['  a  b  ', '  a  b  '], ['', ''], [null, ''], [false, '0'], [true, '1'], ['125.5000', '125.5000']];
    }

    #[DataProvider('badPreview')]
    public function test_bad_preview_values_and_forbidden_source_mode(array $changes, string $path): void
    {
        $data = ['format' => $this->draft(), 'values' => ['DESCRIPTION' => 'x']];
        $this->postJson('/print-kit/preview', array_replace($data, $changes))->assertUnprocessable()->assertJsonValidationErrors($path);
    }

    public static function badPreview(): array
    {
        return [[['driver' => 'mysql'], 'driver'], [['key' => 'P001'], 'key'], [['record' => []], 'record'], [['values' => ['DESCRIPTION' => ['html' => 'x']]], 'values.DESCRIPTION']];
    }

    public function test_invalid_margins_and_category_are_422(): void
    {
        $format = $this->draft(false);
        $format['margins']['left'] = 5;
        $this->postJson('/print-kit/design/validate', $format)->assertUnprocessable()->assertJsonValidationErrors('margins');
        $format['category'] = 'RP';
        $this->postJson('/print-kit/design/validate', $format)->assertUnprocessable()->assertJsonValidationErrors('category');
    }

    public function test_nested_preview_requires_each_margin_and_escapes_literal_scripts(): void
    {
        $format = $this->draft(false);
        unset($format['margins']['left']);
        $this->postJson('/print-kit/preview', ['format' => $format, 'values' => []])->assertUnprocessable()->assertJsonValidationErrors('format.margins.left');
        $format['margins']['left'] = 0;
        $format['detail']['elements'][0]['text'] = '<script>alert(1)</script>';
        $response = $this->postJson('/print-kit/preview', ['format' => $format, 'values' => []])->assertOk();
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $response->getContent());
        $this->assertStringNotContainsString('<script>', $response->getContent());
    }
}
