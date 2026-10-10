<?php

declare(strict_types=1);

namespace Ahinest\LaravelPrintKit\Data;

use Ahinest\LaravelPrintKit\Contracts\PrintKitModel;
use Ahinest\LaravelPrintKit\Data\Exceptions\DuplicatePrintFieldDefinition;
use Ahinest\LaravelPrintKit\Data\Exceptions\InvalidRegistryConfiguration;
use Ahinest\LaravelPrintKit\Data\Exceptions\PrintFieldNotFound;
use Ahinest\LaravelPrintKit\Data\Exceptions\UnknownCategory;
use Ahinest\LaravelPrintKit\Data\Registries\CategoryRegistry;
use Ahinest\LaravelPrintKit\Data\Registries\DriverRegistry;
use Ahinest\LaravelPrintKit\Data\Registries\RegistryValidation;
use Ahinest\LaravelPrintKit\Data\Registries\SystemRegistry;
use Ahinest\LaravelPrintKit\Support\ModelResolver;
use Illuminate\Database\Eloquent\Model;

/** Consulta el catálogo una vez y aplica precedencia exacta antes de evaluar disponibilidad. */
final class PrintFieldResolver
{
    public function __construct(private ModelResolver $models, private DriverRegistry $drivers, private SystemRegistry $systems, private CategoryRegistry $categories) {}

    public function validateContext(string $driver, string $system, string $category): void
    {
        $this->drivers->get($driver);
        $definition = $this->systems->get($system);
        $this->categories->get($category);
        if (! in_array($category, $definition->categories, true)) {
            throw new UnknownCategory('Categoría no habilitada para el sistema.');
        }
    }

    public function fieldsFor(string $driver, string $system, string $category): array
    {
        return $this->query($driver, $system, $category, null, null);
    }

    public function resolve(string $driver, string $system, string $category, string $placeholder): Model&PrintKitModel
    {
        return $this->resolveMany($driver, $system, $category, [$placeholder])[$placeholder];
    }

    public function resolveMany(string $driver, string $system, string $category, array $placeholders, ?string $storageConnection = null): array
    {
        $fields = $this->query($driver, $system, $category, $placeholders, $storageConnection);
        foreach ($placeholders as $placeholder) {
            if (! isset($fields[$placeholder])) {
                throw new PrintFieldNotFound("Comodín no disponible: $driver/$system/$category/$placeholder.");
            }
        }

        return $fields;
    }

    private function query(string $driver, string $system, string $category, ?array $placeholders, ?string $storageConnection): array
    {
        $this->validateContext($driver, $system, $category);
        foreach ($placeholders ?? [] as $placeholder) {
            if (! RegistryValidation::identifier($placeholder)) {
                throw new InvalidRegistryConfiguration('Placeholder inválido.');
            }
        }
        if ($placeholders === []) {
            return [];
        }
        $model = $this->models->make('field');
        if ($model->getConnectionName() === null) {
            $model->setConnection($storageConnection);
        }
        $query = $model->newQuery()->where('driver', $driver)->where('system', $system)->whereIn('category', [$category, '*']);
        if ($placeholders !== null) {
            $query->whereIn('placeholder', array_values(array_unique($placeholders)));
        }
        // Una collation insensible a mayúsculas no debe alterar el contexto solicitado.
        $rows = array_filter($query->get()->all(), fn ($field) => $field->getAttribute('driver') === $driver && $field->getAttribute('system') === $system
            && in_array($field->getAttribute('category'), [$category, '*'], true)
            && ($placeholders === null || in_array($field->getAttribute('placeholder'), $placeholders, true)));

        return self::selectDefinitions($rows, $category);
    }

    /** Detecta identidades duplicadas incluso en tablas externas sin índice UNIQUE. */
    public static function selectDefinitions(array $rows, string $category): array
    {
        $seen = $result = [];
        foreach ($rows as $field) {
            $placeholder = $field->getAttribute('placeholder');
            $fieldCategory = $field->getAttribute('category');
            if (! RegistryValidation::identifier($placeholder) || ! RegistryValidation::identifier($field->getAttribute('associated_field'))) {
                throw new InvalidRegistryConfiguration('La definición contiene un identificador inválido.');
            }
            DataType::parse($field->getAttribute('data_type'));
            $identity = $fieldCategory.'/'.$placeholder;
            if (isset($seen[$identity])) {
                throw new DuplicatePrintFieldDefinition("Definición duplicada: $identity.");
            }
            $seen[$identity] = true;
            if (! isset($result[$placeholder]) || $fieldCategory === $category) {
                $result[$placeholder] = $field;
            }
        }

        return $result;
    }
}
