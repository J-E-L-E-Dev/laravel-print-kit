<?php

declare(strict_types=1);

namespace Ahinest\LaravelPrintKit\Data\Registries;

/** Describe un sistema independiente de la conexión seleccionada. */
final class SystemDefinition extends RegistryEntry
{
    public function __construct(string $code, string $label, string $description, public readonly string $table, public readonly string $key, public readonly array $categories)
    {
        parent::__construct($code, $label, $description);
    }

    public function toArray(): array
    {
        return parent::toArray() + ['table' => $this->table, 'key' => $this->key, 'categories' => $this->categories];
    }
}
