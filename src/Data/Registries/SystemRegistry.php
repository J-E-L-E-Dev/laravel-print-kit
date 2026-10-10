<?php

declare(strict_types=1);

namespace Ahinest\LaravelPrintKit\Data\Registries;

use Ahinest\LaravelPrintKit\Data\Exceptions\InvalidRegistryConfiguration;
use Ahinest\LaravelPrintKit\Data\Exceptions\UnknownSystem;

/** Resuelve tabla y clave validadas; nunca contiene una conexión. */
final class SystemRegistry extends ConfiguredRegistry
{
    protected function kind(): string
    {
        return 'systems';
    }

    protected function unknown(string $code): never
    {
        throw new UnknownSystem("Sistema no registrado: $code.");
    }

    public function get(string $code): SystemDefinition
    {
        return parent::get($code);
    }

    protected function entry(string $code, array $data): SystemDefinition
    {
        if (array_key_exists('driver', $data) || ! RegistryValidation::identifier($data['table'] ?? null)
            || ! RegistryValidation::identifier($data['key'] ?? null) || ! is_array($data['categories'] ?? null)
            || ! array_is_list($data['categories']) || $data['categories'] === []) {
            throw new InvalidRegistryConfiguration("Sistema $code: tabla, clave o categorías inválidas; driver no pertenece al sistema.");
        }
        $categories = (new CategoryRegistry($this->config))->all();
        foreach ($data['categories'] as $category) {
            if (! is_string($category) || ! isset($categories[$category])) {
                throw new InvalidRegistryConfiguration("Sistema $code: categoría no registrada.");
            }
        }

        return new SystemDefinition($code, $data['label'], $data['description'], $data['table'], $data['key'], array_values(array_unique($data['categories'])));
    }
}
