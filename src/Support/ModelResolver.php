<?php

declare(strict_types=1);

namespace Ahinest\LaravelPrintKit\Support;

use Ahinest\LaravelPrintKit\Contracts\PrintKitModel;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/** Centraliza la creación de modelos configurados y comprueba su contrato. */
class ModelResolver
{
    public function __construct(private Repository $config) {}

    public function make(string $name): Model&PrintKitModel
    {
        $class = $this->config->get("print-kit.models.$name");
        if (! is_string($class) || ! is_subclass_of($class, Model::class) || ! is_subclass_of($class, PrintKitModel::class)) {
            throw new InvalidArgumentException("El modelo $name debe extender Eloquent e implementar PrintKitModel.");
        }

        $model = new $class;
        $entity = ['field' => 'fields', 'format' => 'formats', 'paper_size' => 'paper_sizes'][$name] ?? null;
        if ($model->printKitEntity() !== $entity) {
            throw new InvalidArgumentException("La entidad del modelo $name no coincide con su configuración.");
        }

        return $model;
    }
}
