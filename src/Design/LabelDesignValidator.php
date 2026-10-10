<?php

declare(strict_types=1);

namespace Ahinest\LaravelPrintKit\Design;

use Ahinest\LaravelPrintKit\Contracts\PrintKitModel;
use Ahinest\LaravelPrintKit\Data\PrintFieldResolver;
use Ahinest\LaravelPrintKit\Support\InvalidPaperSize;
use Ahinest\LaravelPrintKit\Support\ModelResolver;
use Ahinest\LaravelPrintKit\Support\PaperSizeResolver;
use Illuminate\Database\Eloquent\Model;

/** Valida el diseño contra el catálogo configurado sin consultar el origen de datos de los comodines. */
class LabelDesignValidator
{
    public function __construct(private ModelResolver $models, private PaperSizeResolver $papers) {}

    public function validate(LabelDesign $design, Model&PrintKitModel $format, ?Model $paper = null): void
    {
        if ($format->printKitEntity() !== 'formats') {
            throw new InvalidDesign('format', 'Se requiere un modelo de formatos.');
        }
        if ($format->getAttribute('category') !== 'ET') {
            throw new InvalidDesign('category', 'Esta versión solo admite etiquetas ET.');
        }
        if (! in_array($format->getAttribute('orientation'), ['V', 'H'], true)) {
            throw new InvalidDesign('orientation', 'Solo se admiten V y H.');
        }
        $system = $format->getAttribute('system');
        if (! is_string($system) || strlen($system) !== 3) {
            throw new InvalidDesign('system', 'Se requiere un sistema de tres caracteres.');
        }

        try {
            $paper = $this->papers->resolve($format, $paper, false);
        } catch (InvalidPaperSize $exception) {
            throw new InvalidDesign('paper_size_id', $exception->getMessage());
        }

        $geometry = LabelGeometry::fromPaper($paper->getAttribute('width'), $paper->getAttribute('height'),
            $format->getAttribute('orientation'), $format->getAttribute('margins'));
        $geometry->validate($design);

        $placeholders = [];
        foreach ($design->elements() as $element) {
            if ($element instanceof FieldElement) {
                $placeholders[] = $element->field();
            }
        }

        if ($placeholders === []) {
            return;
        }

        $fieldModel = $this->relatedModel('field', $format);
        $rows = $fieldModel->newQuery()
            ->whereIn('placeholder', array_values(array_unique($placeholders)))
            ->where('system', $system)
            ->whereIn('category', ['ET', '*'])
            ->get()
            ->all();
        $byDriver = [];
        foreach ($rows as $row) {
            $byDriver[$row->getAttribute('driver')][] = $row;
        }
        $available = [];
        foreach ($byDriver as $definitions) {
            foreach (PrintFieldResolver::selectDefinitions($definitions, 'ET') as $placeholder => $definition) {
                if ($definition->getAttribute('usable_in_detail')) {
                    $available[] = $placeholder;
                }
            }
        }

        foreach ($design->elements() as $index => $element) {
            if ($element instanceof FieldElement && ! in_array($element->field(), $available, true)) {
                throw new InvalidDesign("detail.elements.$index.field", 'El comodín no existe o no está habilitado para el sistema y el área libre de esta etiqueta.');
            }
        }
    }

    /** Reproduce la herencia de conexión de Eloquent y respeta conexiones explícitas de sustitutos. */
    private function relatedModel(string $name, Model $format): Model&PrintKitModel
    {
        $model = $this->models->make($name);
        if ($model->getConnectionName() === null) {
            $model->setConnection($format->getConnectionName());
        }

        return $model;
    }
}
