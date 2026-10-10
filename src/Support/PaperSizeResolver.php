<?php

declare(strict_types=1);

namespace Ahinest\LaravelPrintKit\Support;

use Ahinest\LaravelPrintKit\Contracts\PrintKitModel;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Database\Eloquent\Model;

/** Resuelve y verifica el papel; permite compartir una lectura explícita entre validación y render. */
final class PaperSizeResolver
{
    public function __construct(private ModelResolver $models, private Repository $config) {}

    public function resolve(Model&PrintKitModel $format, ?Model $paper = null, bool $useLoadedRelation = true): Model&PrintKitModel
    {
        $expected = $this->models->make('paper_size');
        if ($expected->getConnectionName() === null) {
            $expected->setConnection($format->getConnectionName());
        }
        $id = $format->getAttribute('paper_size_id');
        if ($paper === null) {
            $paper = $useLoadedRelation && $format->relationLoaded('paperSize') ? $format->getRelation('paperSize') : $expected->newQuery()->where('id', $id)->first();
        }
        if (! $paper instanceof Model || ! $paper instanceof PrintKitModel || ! is_a($paper, $expected::class)
            || $paper->printKitEntity() !== 'paper_sizes' || (string) $paper->getAttribute('id') !== (string) $id
            || ($paper->getConnectionName() ?? $this->config->get('database.default')) !== ($expected->getConnectionName() ?? $this->config->get('database.default'))) {
            throw new InvalidPaperSize('El papel no existe o no coincide con el modelo, clave y conexión del formato.');
        }

        return $paper;
    }
}
