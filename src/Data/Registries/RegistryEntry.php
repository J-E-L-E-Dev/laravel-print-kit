<?php

declare(strict_types=1);

namespace Ahinest\LaravelPrintKit\Data\Registries;

/** Expone metadatos validados para consumidores y futuros selectores. */
class RegistryEntry implements \JsonSerializable
{
    public function __construct(public readonly string $code, public readonly string $label, public readonly string $description) {}

    public function toArray(): array
    {
        return ['value' => $this->code, 'label' => $this->label, 'description' => $this->description];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
