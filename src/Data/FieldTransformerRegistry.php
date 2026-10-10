<?php

declare(strict_types=1);

namespace Ahinest\LaravelPrintKit\Data;

use Ahinest\LaravelPrintKit\Contracts\FieldTransformer;
use Ahinest\LaravelPrintKit\Data\Exceptions\InvalidRegistryConfiguration;
use Ahinest\LaravelPrintKit\Data\Registries\CategoryRegistry;
use Ahinest\LaravelPrintKit\Data\Registries\DriverRegistry;
use Ahinest\LaravelPrintKit\Data\Registries\RegistryValidation;
use Ahinest\LaravelPrintKit\Data\Registries\SystemRegistry;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Container\Container;

/** Prioriza driver/exacta, driver/*, genérico/exacta y genérico/*, en ese orden. */
final class FieldTransformerRegistry
{
    private array $registered = [];

    public function __construct(private Repository $config, private Container $container, private DriverRegistry $drivers, private SystemRegistry $systems, private CategoryRegistry $categories) {}

    public function register(string $system, string $category, string $placeholder, FieldTransformer|string $transformer, ?string $driver = null): void
    {
        $this->validate($driver, $system, $category, $placeholder, $transformer);
        $this->registered[$this->identity($driver, $system, $category, $placeholder)] = $transformer;
    }

    private function identity(?string $driver, string $system, string $category, string $placeholder): string
    {
        return ($driver ?? '').'/'.$system.'/'.$category.'/'.$placeholder;
    }

    private function validate(?string $driver, string $system, string $category, string $placeholder, mixed $transformer): void
    {
        if ($driver !== null) {
            $this->drivers->get($driver);
        }
        $this->systems->get($system);
        if ($category !== '*') {
            $this->categories->get($category);
        }
        if (! RegistryValidation::identifier($placeholder) || (! $transformer instanceof FieldTransformer
            && (! is_string($transformer) || ! is_a($transformer, FieldTransformer::class, true)))) {
            throw new InvalidRegistryConfiguration('Transformer o placeholder inválido.');
        }
    }

    public function get(string $driver, string $system, string $category, string $placeholder): ?FieldTransformer
    {
        $this->drivers->get($driver);
        $this->systems->get($system);
        $this->categories->get($category);
        $entries = [];
        $config = $this->config->get('print-kit.transformers', []);
        if (! is_array($config) || array_diff(array_keys($config), ['drivers', 'systems']) !== []) {
            throw new InvalidRegistryConfiguration('Mapa de transformers inválido.');
        }
        $groups = $config['drivers'] ?? [];
        if (! is_array($groups) || ! is_array($config['systems'] ?? [])) {
            throw new InvalidRegistryConfiguration('Grupos de transformers inválidos.');
        }
        foreach (array_keys($groups) as $configuredDriver) {
            if (! RegistryValidation::code('drivers', $configuredDriver)) {
                throw new InvalidRegistryConfiguration('Nombre de driver inválido en transformers.');
            }
            $this->drivers->get($configuredDriver);
        }
        $groups[''] = $config['systems'] ?? [];
        foreach ($groups as $configuredDriver => $systems) {
            if (! is_array($systems) || ! is_string($configuredDriver)) {
                throw new InvalidRegistryConfiguration('Grupo de transformers inválido.');
            }
            foreach ($systems as $configuredSystem => $categories) {
                if (! is_string($configuredSystem) || ! is_array($categories)) {
                    throw new InvalidRegistryConfiguration('Sistema de transformers inválido.');
                }
                $this->systems->get($configuredSystem);
                foreach ($categories as $configuredCategory => $fields) {
                    if (! is_string($configuredCategory) || ! is_array($fields)) {
                        throw new InvalidRegistryConfiguration('Categoría de transformers inválida.');
                    }
                    if ($configuredCategory !== '*') {
                        $this->categories->get($configuredCategory);
                    }
                    foreach ($fields as $configuredPlaceholder => $transformer) {
                        if (! is_string($configuredPlaceholder) || ! is_string($transformer)) {
                            throw new InvalidRegistryConfiguration('La configuración requiere clases de transformer.');
                        }
                        $d = $configuredDriver === '' ? null : $configuredDriver;
                        $this->validate($d, $configuredSystem, $configuredCategory, $configuredPlaceholder, $transformer);
                        $entries[$this->identity($d, $configuredSystem, $configuredCategory, $configuredPlaceholder)] = $transformer;
                    }
                }
            }
        }
        $entries = array_replace($entries, $this->registered);
        foreach ([[$driver, $category], [$driver, '*'], [null, $category], [null, '*']] as [$d, $c]) {
            $entry = $entries[$this->identity($d, $system, $c, $placeholder)] ?? null;
            if ($entry !== null) {
                return $entry instanceof FieldTransformer ? $entry : $this->container->make($entry);
            }
        }

        return null;
    }
}
