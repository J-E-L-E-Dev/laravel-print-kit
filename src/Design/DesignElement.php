<?php

declare(strict_types=1);

namespace Ahinest\LaravelPrintKit\Design;

use JsonSerializable;

/** Define una caja en centímetros con origen en la esquina superior izquierda. */
abstract class DesignElement implements JsonSerializable
{
    protected function __construct(
        public readonly string $id,
        public readonly Decimal $x,
        public readonly Decimal $y,
        public readonly Decimal $width,
        public readonly Decimal $height,
        public readonly ?ElementStyle $style,
        protected readonly string $content,
    ) {}

    /** Valida la forma completa antes de construir cualquier elemento concreto. */
    final public static function fromArray(array $data, string $path = 'element'): FieldElement|TextElement
    {
        $type = $data['type'] ?? null;
        if (! in_array($type, ['field', 'text'], true)) {
            throw new InvalidDesign("$path.type", 'Solo se admiten elementos field y text.');
        }
        if ((static::class === FieldElement::class && $type !== 'field') ||
            (static::class === TextElement::class && $type !== 'text')) {
            throw new InvalidDesign("$path.type", 'El tipo no coincide con la clase solicitada.');
        }

        $contentKey = $type === 'field' ? 'field' : 'text';
        $allowed = ['id', 'type', $contentKey, 'x', 'y', 'width', 'height', 'style'];
        if ($type === 'field') {
            $allowed[] = 'render_as';
        }
        foreach (array_keys($data) as $key) {
            if (! in_array($key, $allowed, true)) {
                throw new InvalidDesign("$path.$key", 'Propiedad de elemento no soportada.');
            }
        }
        $id = $data['id'] ?? null;
        if (! is_string($id) || trim($id) === '' || strlen($id) > 128 || ! mb_check_encoding($id, 'UTF-8')) {
            throw new InvalidDesign("$path.id", 'Se requiere un identificador string no vacío de hasta 128 bytes.');
        }
        $content = $data[$contentKey] ?? null;
        if (! is_string($content) || ! mb_check_encoding($content, 'UTF-8')) {
            throw new InvalidDesign("$path.$contentKey", 'Se requiere una cadena UTF-8.');
        }
        if ($type === 'field' && (strlen($content) > 100 || ! preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/D', $content))) {
            throw new InvalidDesign("$path.field", 'Se requiere un placeholder de hasta 100 caracteres.');
        }
        if (array_key_exists('style', $data) && ! is_array($data['style'])) {
            throw new InvalidDesign("$path.style", 'El estilo debe ser un objeto de propiedades.');
        }

        $arguments = [
            $id,
            Decimal::from($data['x'] ?? null, "$path.x"),
            Decimal::from($data['y'] ?? null, "$path.y"),
            Decimal::from($data['width'] ?? null, "$path.width", true),
            Decimal::from($data['height'] ?? null, "$path.height", true),
            isset($data['style']) ? ElementStyle::fromArray($data['style'], "$path.style") : null,
            $content,
        ];
        if ($type === 'field') {
            $representation = array_key_exists('render_as', $data) ? $data['render_as'] : 'text';
            if (! in_array($representation, ['text', 'qr'], true)) {
                throw new InvalidDesign("$path.render_as", 'Solo se admiten text y qr.');
            }

            return new FieldElement(...[...$arguments, $representation]);
        }

        return new TextElement(...$arguments);
    }

    abstract public function type(): string;

    final public function toArray(): array
    {
        $data = [
            'id' => $this->id, 'type' => $this->type(), $this->type() => $this->content,
            'x' => (string) $this->x, 'y' => (string) $this->y,
            'width' => (string) $this->width, 'height' => (string) $this->height,
        ];
        if ($this->style !== null && $this->style->toArray() !== []) {
            $data['style'] = $this->style->toArray();
        }
        if ($this instanceof FieldElement && $this->renderAs() === 'qr') {
            $data['render_as'] = 'qr';
        }

        return $data;
    }

    final public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
