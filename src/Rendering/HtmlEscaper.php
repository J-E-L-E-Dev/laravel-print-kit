<?php

declare(strict_types=1);

namespace Ahinest\LaravelPrintKit\Rendering;

/** Escapa texto y atributos; nunca reconoce HTML previo ni evita el doble escape de entidades literales. */
final class HtmlEscaper
{
    public static function escape(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8', true);
    }

    public static function value(string|int|float|bool|null $value): string
    {
        return self::escape(self::text($value));
    }

    /** Conversión del valor final compartida por texto y QR, sin trim ni coerción numérica. */
    public static function text(string|int|float|bool|null $value): string
    {
        return match (true) {
            $value === null => '', $value === true => '1', $value === false => '0', default => (string) $value
        };
    }
}
