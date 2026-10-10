<?php

declare(strict_types=1);

namespace Ahinest\LaravelPrintKit\Http;

use Ahinest\LaravelPrintKit\Contracts\PrintKitModel;
use Ahinest\LaravelPrintKit\Data\ProvidedValuesResolver;
use Ahinest\LaravelPrintKit\Data\Registries\CategoryRegistry;
use Ahinest\LaravelPrintKit\Data\Registries\SystemRegistry;
use Ahinest\LaravelPrintKit\Design\InvalidDesign;
use Ahinest\LaravelPrintKit\Design\LabelDesign;
use Ahinest\LaravelPrintKit\Design\LabelDesignValidator;
use Ahinest\LaravelPrintKit\Http\Requests\ApiInput;
use Ahinest\LaravelPrintKit\Rendering\HtmlDocumentRenderer;
use Ahinest\LaravelPrintKit\Rendering\LabelHtmlRenderer;
use Ahinest\LaravelPrintKit\Rendering\LabelRenderContextFactory;
use Ahinest\LaravelPrintKit\Rendering\RenderedDocument;
use Ahinest\LaravelPrintKit\Support\ModelResolver;
use Ahinest\LaravelPrintKit\Support\PaperSizeResolver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/** Coordina escrituras de instancia y borradores; comparte papel entre validación y preview sin persistir borradores. */
final class FormatManager
{
    public function __construct(private ModelResolver $models, private PaperSizeResolver $papers, private LabelDesignValidator $designs,
        private SystemRegistry $systems, private CategoryRegistry $categories, private ProvidedValuesResolver $values,
        private LabelRenderContextFactory $contexts, private LabelHtmlRenderer $labels, private HtmlDocumentRenderer $documents) {}

    public function find(string $id): Model&PrintKitModel
    {
        return $this->models->make('format')->newQuery()->where('id', $id)->first() ?? throw new NotFoundHttpException('Formato inexistente.');
    }

    public function temporary(array $attributes): Model&PrintKitModel
    {
        $format = $this->models->make('format');
        foreach (config('print-kit.defaults.formats', []) as $key => $value) {
            if (! array_key_exists($key, $format->getAttributes())) {
                $format->setAttribute($key, $value);
            }
        }
        $this->assign($format, $attributes);

        return $format;
    }

    private function assign(Model&PrintKitModel $format, array $attributes): void
    {
        foreach ($attributes as $key => $value) {
            if (! in_array($key, ApiInput::EDITABLE, true)) {
                throw ValidationException::withMessages([$key => ['Propiedad no editable.']]);
            }
            if ($key === 'paper_size_id' && (string) $format->getAttribute($key) !== (string) $value) {
                $format->unsetRelation('paperSize');
            }
            $format->setAttribute($key, $value);
        }
    }

    public function paper(Model&PrintKitModel $format): Model&PrintKitModel
    {
        return $this->papers->resolve($format);
    }

    public function validate(Model&PrintKitModel $format, ?Model $paper = null): void
    {
        $category = (string) $format->getAttribute('category');
        if ($category !== 'ET') {
            throw new InvalidDesign('category', 'El diseñador solo admite ET.');
        }
        $this->categories->get($category);
        $system = $this->systems->get((string) $format->getAttribute('system'));
        if (! in_array($category, $system->categories, true)) {
            throw new InvalidDesign('category', 'La categoría no está habilitada para el sistema.');
        }
        $detail = $format->getAttribute('detail');
        if (! is_array($detail)) {
            throw new InvalidDesign('detail', 'Se requiere un diseño completo.');
        }
        $paper ??= $this->paper($format);
        $this->designs->validate(LabelDesign::fromArray($detail), $format, $paper);
    }

    public function create(array $attributes): Model&PrintKitModel
    {
        return $this->persist($this->temporary($attributes));
    }

    public function update(Model&PrintKitModel $format, array $attributes): Model&PrintKitModel
    {
        $this->assign($format, $attributes);

        return $this->persist($format);
    }

    private function codeExists(Model&PrintKitModel $format): bool
    {
        $query = $format->newQueryWithoutScopes()->where('code', $format->getAttribute('code'));
        if ($format->exists) {
            $query->where('id', '!=', $format->getAttribute('id'));
        }

        return $query->exists();
    }

    private function uniqueCode(Model&PrintKitModel $format): void
    {
        if ($this->codeExists($format)) {
            throw ValidationException::withMessages(['code' => ['El código ya está registrado.']]);
        }
    }

    private function persist(Model&PrintKitModel $format): Model&PrintKitModel
    {
        $this->uniqueCode($format);
        $paper = $this->paper($format);
        $this->validate($format, $paper);
        try {
            if (! $format->save()) {
                throw ValidationException::withMessages(['format' => ['El modelo rechazó el guardado.']]);
            }
        } catch (QueryException $exception) {
            $this->uniqueCode($format);
            throw $exception;
        }
        $format->setRelation('paperSize', $paper);

        return $format;
    }

    public function delete(Model&PrintKitModel $format): void
    {
        if (! $format->delete()) {
            throw ValidationException::withMessages(['format' => ['El modelo rechazó la eliminación.']]);
        }
    }

    public function preview(Model&PrintKitModel $format, array $values): RenderedDocument
    {
        $paper = $this->paper($format);
        $this->validate($format, $paper);
        $resolved = $this->values->resolve($format, $values);
        $context = $this->contexts->create($format, $paper);

        return $this->documents->render($this->labels->render($context, $resolved));
    }
}
