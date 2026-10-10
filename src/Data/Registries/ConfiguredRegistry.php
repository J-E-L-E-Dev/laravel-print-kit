<?php

declare(strict_types=1);

namespace Ahinest\LaravelPrintKit\Data\Registries;

use Ahinest\LaravelPrintKit\Data\Exceptions\InvalidRegistryConfiguration;
use Illuminate\Contracts\Config\Repository;

/** Valida el registro completo sin almacenar copias obsoletas de configuración. */
abstract class ConfiguredRegistry
{
    public function __construct(protected Repository $config) {}

    abstract protected function kind(): string;

    abstract protected function unknown(string $code): never;

    protected function entry(string $code, array $data): RegistryEntry
    {
        return new RegistryEntry($code, $data['label'], $data['description']);
    }

    public function all(): array
    {
        $kind = $this->kind();
        $data = $this->config->get("print-kit.$kind", []);
        if (! is_array($data)) {
            throw new InvalidRegistryConfiguration("print-kit.$kind debe ser un mapa.");
        }
        $entries = [];
        foreach ($data as $code => $definition) {
            RegistryValidation::entry($kind, $code, $definition);
            $entries[$code] = $this->entry($code, $definition);
        }

        return $entries;
    }

    public function get(string $code): RegistryEntry
    {
        return $this->all()[$code] ?? $this->unknown($code);
    }
}
