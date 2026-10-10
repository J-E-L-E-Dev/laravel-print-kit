<?php

declare(strict_types=1);

namespace Ahinest\LaravelPrintKit\Data;

use Ahinest\LaravelPrintKit\Contracts\PrintKitModel;
use Ahinest\LaravelPrintKit\Data\Exceptions\PrintFieldNotFound;
use Ahinest\LaravelPrintKit\Data\Exceptions\TransformerNotFound;
use Illuminate\Database\Eloquent\Model;

/** Consulta el catálogo una vez y procesa el registro en memoria sin consultar su origen. */
final class ProvidedRecordResolver
{
    public function __construct(private PrintFieldResolver $fields, private FieldTransformerRegistry $transformers, private RecordAccessor $accessor, private DataTypeFormatter $formatter) {}

    public function resolve(Model&PrintKitModel $format, string $driver, mixed $record): ResolvedLabel
    {
        return $this->resolvePrepared($this->prepare($format, $driver), $record);
    }

    /** Prepara solo una vez los metadatos y transformers de todos los placeholders utilizados. */
    public function prepare(Model&PrintKitModel $format, string $driver, ResolutionMode $mode = ResolutionMode::ProvidedRecord): ResolutionPlan
    {
        $design = FormatInput::design($format);
        $system = (string) $format->getAttribute('system');
        $context = new ResolutionContext($driver, $system, 'ET', $mode);
        $fields = $this->fields->resolveMany($driver, $system, 'ET', FormatInput::placeholders($design), $format->getConnectionName());
        $transformers = [];
        foreach ($fields as $placeholder => $field) {
            if (! $field->getAttribute('usable_in_detail')) {
                throw new PrintFieldNotFound("El comodín $placeholder no está habilitado en el área libre.");
            }
            $transformers[$placeholder] = $this->transformers->get($driver, $system, 'ET', $placeholder);
            if (DataType::parse($field->getAttribute('data_type'))->kind === 'T' && $transformers[$placeholder] === null) {
                throw new TransformerNotFound("El comodín $placeholder requiere un transformer.");
            }
        }

        return new ResolutionPlan($design, $context, $fields, $transformers);
    }

    /** Aplica transformaciones a una copia de la definición para no contaminar otras etiquetas. */
    public function resolvePrepared(ResolutionPlan $plan, mixed $record, string|int|null $key = null): ResolvedLabel
    {
        $context = new ResolutionContext($plan->context->driver, $plan->context->system, $plan->context->category, $plan->context->mode, $key);
        $values = [];
        foreach ($plan->fields as $placeholder => $field) {
            $type = DataType::parse($field->getAttribute('data_type'));
            $value = $this->accessor->get($record, $field->getAttribute('associated_field'));
            $transformer = $plan->transformers[$placeholder];
            if ($transformer !== null) {
                $value = $transformer->transform($value, $record, clone $field, $context);
            }
            $values[$placeholder] = $type->kind === 'T' ? $value : $this->formatter->format($value, $type);
        }

        return new ResolvedLabel($plan->design, $values, $context);
    }
}
