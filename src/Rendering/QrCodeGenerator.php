<?php

declare(strict_types=1);

namespace Ahinest\LaravelPrintKit\Rendering;

use Ahinest\LaravelPrintKit\Rendering\Exceptions\QrCodeGenerationFailed;
use BaconQrCode\Common\ErrorCorrectionLevel;
use BaconQrCode\Exception\InvalidArgumentException;
use BaconQrCode\Exception\WriterException;
use BaconQrCode\Renderer\Color\Rgb;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\Fill;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

/** SVG íntegramente en memoria: negro/blanco, cuatro módulos de quiet zone, corrección M y UTF-8. */
class QrCodeGenerator
{
    public function generateDataUri(string $value, string $elementId = '', string $placeholder = ''): string
    {
        if (preg_match('//u', $value) !== 1) {
            throw new QrCodeGenerationFailed($elementId, $placeholder);
        }
        try {
            $fill = Fill::uniformColor(new Rgb(255, 255, 255), new Rgb(0, 0, 0));
            $writer = new Writer(new ImageRenderer(new RendererStyle(256, 4, null, null, $fill), new SvgImageBackEnd));
            $svg = $writer->writeString($value, 'UTF-8', ErrorCorrectionLevel::M());
        } catch (WriterException|InvalidArgumentException) {
            throw new QrCodeGenerationFailed($elementId, $placeholder);
        }

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }
}
