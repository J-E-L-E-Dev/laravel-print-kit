<?php

declare(strict_types=1);

namespace Ahinest\LaravelPrintKit\Tests\Rendering;

use Ahinest\LaravelPrintKit\Rendering\Exceptions\IncompatibleRenderedLabels;
use Ahinest\LaravelPrintKit\Rendering\HtmlDocumentRenderer;
use Ahinest\LaravelPrintKit\Rendering\LabelHtmlRenderer;

class HtmlDocumentRendererTest extends RenderingTestCase
{
    public function test_single_document_and_print_css(): void
    {
        $label = (new LabelHtmlRenderer)->render($this->context(orientation: 'H'), $this->label());
        $document = (new HtmlDocumentRenderer)->render($label);
        $this->assertStringStartsWith('<!DOCTYPE html>', $document->html());
        $dom = $this->dom($document->html());
        $this->assertSame(1, $dom->query('//meta[@charset="utf-8"]')->length);
        $this->assertSame(1, $dom->query('//body/div[@class="print-kit-label"]')->length);
        $css = $dom->query('//style')->item(0)->textContent;
        $this->assertStringContainsString('@page{size:3.0000cm 5.0000cm;margin:0;}', $css);
        $this->assertStringContainsString('html,body{margin:0;padding:0;}', $css);
        $this->assertStringContainsString('@media print', $css);
        $this->assertStringContainsString('break-after:page;page-break-after:always;', $css);
        $this->assertStringContainsString('.print-kit-label:last-child{break-after:auto;page-break-after:auto;}', $css);
        $this->assertStringNotContainsString('break-before', $css);
        $this->assertSame(1, $document->count);
        $this->assertSame('text/html', $document->mediaType);
        $this->assertSame('utf-8', $document->charset);
        $this->assertSame('3.0000', $document->width);
        $this->assertSame('5.0000', $document->height);
        $this->assertSame($document->toArray(), json_decode(json_encode($document), true));
    }

    public function test_order_and_duplicates(): void
    {
        $labels = [];
        $renderer = new LabelHtmlRenderer;
        foreach (['P003', 'P001', 'P002', 'P001'] as $value) {
            $labels[] = $renderer->render($this->context(), $this->label($value));
        }
        $document = (new HtmlDocumentRenderer)->render($labels);
        $nodes = $this->dom($document->html())->query('//div[@data-element-id="value"]');
        $this->assertSame(['P003', 'P001', 'P002', 'P001'], array_map(fn ($node) => $node->textContent, iterator_to_array($nodes)));
        $this->assertSame(4, $document->count);
    }

    public function test_rejects_mixed_physical_sizes(): void
    {
        $renderer = new LabelHtmlRenderer;
        $label = $this->label();
        $this->expectException(IncompatibleRenderedLabels::class);
        (new HtmlDocumentRenderer)->render([$renderer->render($this->context(), $label), $renderer->render($this->context(orientation: 'H'), $label)]);
    }

    public function test_context_must_match_physical_sizes(): void
    {
        $label = (new LabelHtmlRenderer)->render($this->context(), $this->label());
        $this->expectException(IncompatibleRenderedLabels::class);
        (new HtmlDocumentRenderer)->render([$label], $this->context(orientation: 'H'));
    }

    public function test_no_raw_html_input(): void
    {
        $this->expectException(IncompatibleRenderedLabels::class);
        (new HtmlDocumentRenderer)->render(['<script>alert(1)</script>']);
    }

    public function test_no_unordered_map(): void
    {
        $label = (new LabelHtmlRenderer)->render($this->context(), $this->label());
        $this->expectException(IncompatibleRenderedLabels::class);
        (new HtmlDocumentRenderer)->render(['label' => $label]);
    }

    public function test_empty_collection_with_context(): void
    {
        $document = (new HtmlDocumentRenderer)->render([], $this->context());
        $this->assertSame(0, $document->count);
        $this->assertSame(0, $this->dom($document->html())->query('//body/div')->length);
        $this->assertSame('5.0000', $document->width);
    }

    public function test_empty_collection_requires_context(): void
    {
        $this->expectException(IncompatibleRenderedLabels::class);
        (new HtmlDocumentRenderer)->render([]);
    }

    public function test_result_cannot_be_mutated(): void
    {
        $document = (new HtmlDocumentRenderer)->render([], $this->context());
        $this->expectException(\Error::class);
        $document->count = 99;
    }
}
