<?php

declare(strict_types=1);

namespace Ahinest\LaravelPrintKit\Design;

use Ahinest\LaravelPrintKit\Contracts\PrintKitModel;
use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Model;
use JsonSerializable;

/** Representa un área libre de etiqueta; el orden de los elementos define su orden de dibujo. */
final class LabelDesign implements JsonSerializable
{
    public const SCHEMA_VERSION = 1;

    /** @param list<FieldElement|TextElement> $items Elementos inmutables en orden de renderizado. */
    private function __construct(private readonly array $items) {}

    public static function empty(): self
    {
        return new self([]);
    }

    public static function fromArray(array $data): self
    {
        if (($data['schema_version'] ?? null) !== self::SCHEMA_VERSION) {
            throw new InvalidDesign('detail.schema_version', 'Solo se admite el entero 1.');
        }
        foreach (array_keys($data) as $key) {
            if (! in_array($key, ['schema_version', 'elements'], true)) {
                throw new InvalidDesign("detail.$key", 'Propiedad de diseño no soportada.');
            }
        }
        if (! isset($data['elements']) || ! is_array($data['elements']) || ! array_is_list($data['elements'])) {
            throw new InvalidDesign('detail.elements', 'Se requiere una lista ordenada de elementos.');
        }

        $elements = [];
        $ids = [];
        foreach ($data['elements'] as $index => $dataElement) {
            $path = "detail.elements.$index";
            if (! is_array($dataElement)) {
                throw new InvalidDesign($path, 'Se requiere un objeto de elemento.');
            }
            $element = DesignElement::fromArray($dataElement, $path);
            if (in_array($element->id, $ids, true)) {
                throw new InvalidDesign("$path.id", 'El identificador está repetido en el diseño.');
            }
            $ids[] = $element->id;
            $elements[] = $element;
        }

        return new self($elements);
    }

    /** @return list<FieldElement|TextElement> Elementos inmutables en el orden conservado. */
    public function elements(): array
    {
        return $this->items;
    }

    public function toArray(): array
    {
        return [
            'schema_version' => self::SCHEMA_VERSION,
            'elements' => array_map(static fn (DesignElement $element) => $element->toArray(), $this->items),
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    public function withElement(FieldElement|TextElement $element): self
    {
        $data = $this->toArray();
        $data['elements'][] = $element->toArray();

        return self::fromArray($data);
    }

    public function withoutElement(string $id): self
    {
        $this->indexOf($id);

        return new self(array_values(array_filter($this->items, static fn (DesignElement $element) => $element->id !== $id)));
    }

    public function replaceElement(FieldElement|TextElement $element): self
    {
        $items = $this->items;
        $items[$this->indexOf($element->id)] = $element;

        return new self($items);
    }

    /** Exige una permutación completa, sin eliminar ni duplicar elementos accidentalmente. */
    public function reorder(array $ids): self
    {
        foreach ($ids as $id) {
            if (! is_string($id)) {
                throw new InvalidDesign('detail.elements', 'Los identificadores del orden deben ser strings.');
            }
        }
        if (! array_is_list($ids) || count($ids) !== count($this->items) || count(array_unique($ids, SORT_STRING)) !== count($ids)) {
            throw new InvalidDesign('detail.elements', 'El orden debe incluir cada identificador exactamente una vez.');
        }

        $items = [];
        foreach ($ids as $id) {
            $items[] = $this->items[$this->indexOf($id)];
        }

        return new self($items);
    }

    public function validateFor(Model&PrintKitModel $format): void
    {
        Container::getInstance()->make(LabelDesignValidator::class)->validate($this, $format);
    }

    private function indexOf(string $id): int
    {
        foreach ($this->items as $index => $element) {
            if ($element->id === $id) {
                return $index;
            }
        }

        throw new InvalidDesign('detail.elements', "No existe un elemento con identificador $id.");
    }
}
