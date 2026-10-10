<?php

declare(strict_types=1);

use Ahinest\LaravelPrintKit\Data\ResolutionContext;
use Ahinest\LaravelPrintKit\Data\ResolutionMode;
use Ahinest\LaravelPrintKit\Data\ResolvedLabel;
use Ahinest\LaravelPrintKit\Design\LabelDesign;
use Ahinest\LaravelPrintKit\Rendering\HtmlDocumentRenderer;
use Ahinest\LaravelPrintKit\Rendering\LabelHtmlRenderer;
use Ahinest\LaravelPrintKit\Rendering\LabelRenderContext;

require __DIR__.'/../vendor/autoload.php';

// Ejemplo puro: no inicia Laravel, no consulta DB y no abre un navegador.
$design = LabelDesign::fromArray([
    'schema_version' => 1,
    'elements' => [
        ['id' => 'description', 'type' => 'field', 'field' => 'DESCRIPTION', 'x' => '0.2', 'y' => '0.3', 'width' => '4.6', 'height' => '0.8', 'style' => ['font_size' => 10, 'font_weight' => 'bold']],
        ['id' => 'caption', 'type' => 'text', 'text' => 'Precio Bs.', 'x' => '0.2', 'y' => '1.4', 'width' => '2', 'height' => '0.5'],
        ['id' => 'price', 'type' => 'field', 'field' => 'PRICE', 'x' => '2.3', 'y' => '1.4', 'width' => '2.5', 'height' => '0.5', 'style' => ['text_align' => 'right']],
    ],
]);
$context = new LabelRenderContext(
    design: $design,
    paperWidth: '5',
    paperHeight: '3',
    margins: ['top' => '0.2', 'right' => '0.2', 'bottom' => '0.2', 'left' => '0.2'],
);
$renderer = new LabelHtmlRenderer;
$labels = [];
foreach ([
    ['DESCRIPTION' => 'Martillo profesional', 'PRICE' => '125.50'],
    ['DESCRIPTION' => '<b>Texto literal</b>', 'PRICE' => 'Bs. 45,00'],
    ['DESCRIPTION' => 'Español: á, ñ, €', 'PRICE' => null],
] as $values) {
    $resolved = new ResolvedLabel($design, $values, new ResolutionContext(null, 'INV', 'ET', ResolutionMode::ProvidedValues));
    $labels[] = $renderer->render($context, $resolved);
}
$document = (new HtmlDocumentRenderer)->render($labels);

if (isset($argv[1])) {
    if (file_put_contents($argv[1], $document->html()) === false) {
        throw new RuntimeException('No se pudo guardar el ejemplo HTML.');
    }
} else {
    echo $document->html();
}
