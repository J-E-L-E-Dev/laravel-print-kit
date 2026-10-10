<?php

declare(strict_types=1);

namespace Ahinest\LaravelPrintKit\Http\Controllers;

use Ahinest\LaravelPrintKit\Data\Registries\SystemRegistry;
use Ahinest\LaravelPrintKit\Http\FormatManager;
use Ahinest\LaravelPrintKit\Http\Requests\ApiInput;
use Ahinest\LaravelPrintKit\Http\Resources\FormatResource;
use Ahinest\LaravelPrintKit\Support\ModelResolver;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/** Delega CRUD al servicio y usa builders canónicos para filtros limitados y paginación. */
final class FormatsController extends ApiController
{
    public function __construct(private FormatManager $formats, private ApiInput $input) {}

    public function index(Request $request, ModelResolver $models, SystemRegistry $systems): mixed
    {
        $filters = $this->input->query($request, 'index');
        $query = $models->make('format')->newQuery()->select(FormatResource::SUMMARY);
        if (isset($filters['system'])) {
            $systems->get($filters['system']);
            $query->where('system', $filters['system']);
        }
        foreach (['category', 'disabled'] as $key) {
            if (array_key_exists($key, $filters)) {
                $query->where($key, $filters[$key]);
            }
        }
        if (isset($filters['search'])) {
            $search = '%'.$filters['search'].'%';
            $query->where(fn ($q) => $q->where('code', 'like', $search)->orWhere('name', 'like', $search));
        }
        $perPage = $filters['per_page'] ?? min(max(1, (int) config('print-kit.api.per_page', 25)), max(1, (int) config('print-kit.api.max_per_page', 100)));

        return FormatResource::collection($query->orderBy('id')->paginate($perPage, ['*'], 'page', $filters['page'] ?? 1)->withQueryString());
    }

    public function store(Request $request): mixed
    {
        $format = $this->formats->create($this->input->body($request, 'store'));

        return (new FormatResource($format))->withPaper($this->formats->paper($format))->response()->setStatusCode(201);
    }

    public function show(string $format): FormatResource
    {
        $model = $this->formats->find($format);

        return (new FormatResource($model))->withPaper($this->formats->paper($model));
    }

    public function update(Request $request, string $format): FormatResource
    {
        $model = $this->formats->find($format);
        $model = $this->formats->update($model, $this->input->body($request, 'update'));

        return (new FormatResource($model))->withPaper($this->formats->paper($model));
    }

    public function destroy(string $format): Response
    {
        $this->formats->delete($this->formats->find($format));

        return new Response('', 204);
    }
}
