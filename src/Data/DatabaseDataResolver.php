<?php

declare(strict_types=1);

namespace Ahinest\LaravelPrintKit\Data;

use Ahinest\LaravelPrintKit\Contracts\PrintKitModel;
use Ahinest\LaravelPrintKit\Data\Exceptions\InvalidResolvedValue;
use Ahinest\LaravelPrintKit\Data\Exceptions\RecordNotFound;
use Ahinest\LaravelPrintKit\Data\Registries\DriverRegistry;
use Ahinest\LaravelPrintKit\Data\Registries\SystemRegistry;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Eloquent\Model;

/** Consulta registros completos con bindings y reutiliza una sola preparación de catálogo. */
final class DatabaseDataResolver
{
    public function __construct(private DatabaseManager $database, private DriverRegistry $drivers, private SystemRegistry $systems, private ProvidedRecordResolver $records, private PrintFieldResolver $fields) {}

    public function resolveByKey(Model&PrintKitModel $format, string $driver, mixed $key): ResolvedLabel
    {
        foreach ($this->resolveByKeys($format, $driver, [$key]) as $label) {
            return $label;
        }
        throw new \LogicException('La resolución individual debe producir una etiqueta.');
    }

    public function resolveByKeys(Model&PrintKitModel $format, string $driver, array $keys): ResolvedLabelCollection
    {
        $this->drivers->get($driver);
        $system = $this->systems->get((string) $format->getAttribute('system'));
        $this->fields->validateContext($driver, $system->code, (string) $format->getAttribute('category'));
        FormatInput::design($format);
        if (! array_is_list($keys)) {
            throw new InvalidResolvedValue('Las claves deben formar una lista.');
        }
        foreach ($keys as $key) {
            if ((! is_string($key) && ! is_int($key)) || $key === '') {
                throw new InvalidResolvedValue('Cada clave debe ser un entero o texto no vacío.');
            }
        }
        if ($keys === []) {
            return new ResolvedLabelCollection;
        }
        $plan = $this->records->prepare($format, $driver, ResolutionMode::Database);
        $records = [];
        foreach (array_chunk(array_values(array_unique($keys, SORT_STRING)), 500) as $chunk) {
            $rows = $this->database->connection($driver)->table($system->table)->whereIn($system->key, $chunk)->get();
            foreach ($rows as $row) {
                $identity = (string) $row->{$system->key};
                if (array_key_exists($identity, $records)) {
                    throw new InvalidResolvedValue('La clave del sistema no identifica un registro único.');
                }
                $records[$identity] = $row;
            }
        }
        foreach ($keys as $key) {
            if (! array_key_exists((string) $key, $records)) {
                throw new RecordNotFound($system->code, $driver, $system->key, $key);
            }
        }
        $labels = [];
        foreach ($keys as $key) {
            $labels[] = $this->records->resolvePrepared($plan, $records[(string) $key], $key);
        }

        return new ResolvedLabelCollection(...$labels);
    }
}
