<?php

declare(strict_types=1);

namespace Ahinest\LaravelPrintKit\Tests\Rendering;

use Ahinest\LaravelPrintKit\Data\ResolutionContext;
use Ahinest\LaravelPrintKit\Data\ResolutionMode;
use Ahinest\LaravelPrintKit\Data\ResolvedLabel;
use Ahinest\LaravelPrintKit\Design\LabelDesign;
use Ahinest\LaravelPrintKit\Rendering\LabelRenderContext;
use PHPUnit\Framework\TestCase;

/** Construye datos puros; estas pruebas no inicializan Laravel ni una conexión. */
abstract class RenderingTestCase extends TestCase
{
    protected function design(array $changes = []): LabelDesign
    {
        return LabelDesign::fromArray(['schema_version' => 1, 'elements' => [
            array_replace(['id' => 'value', 'type' => 'field', 'field' => 'VALUE', 'x' => '0.2', 'y' => '0.3', 'width' => '2', 'height' => '0.5'], $changes),
            ['id' => 'caption', 'type' => 'text', 'text' => 'Precio Bs. ñ €', 'x' => '0.2', 'y' => '1', 'width' => '2', 'height' => '0.5'],
        ]]);
    }

    protected function label(mixed $value = '125.50', ?LabelDesign $design = null, string $system = 'INV', string $category = 'ET'): ResolvedLabel
    {
        return new ResolvedLabel($design ?? $this->design(), ['VALUE' => $value], new ResolutionContext(null, $system, $category, ResolutionMode::ProvidedValues));
    }

    protected function context(?LabelDesign $design = null, string $orientation = 'V'): LabelRenderContext
    {
        return new LabelRenderContext($design ?? $this->design(), '5', '3', $orientation, ['top' => '0.2', 'right' => '0.2', 'bottom' => '0.2', 'left' => '0.2']);
    }

    protected function dom(string $html): \DOMXPath
    {
        $document = new \DOMDocument;
        $document->loadHTML(str_contains($html, '<!DOCTYPE') ? $html : '<!DOCTYPE html><html><head><meta charset="utf-8"></head><body>'.$html.'</body></html>');

        return new \DOMXPath($document);
    }

    protected function styles(\DOMNode $node): array
    {
        $styles = [];
        foreach (explode(';', $node->attributes->getNamedItem('style')->nodeValue) as $rule) {
            if ($rule !== '') {
                [$key,$value] = explode(':', $rule, 2);
                $styles[$key] = $value;
            }
        }

        return $styles;
    }
}
