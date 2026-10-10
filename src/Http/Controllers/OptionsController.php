<?php

declare(strict_types=1);

namespace Ahinest\LaravelPrintKit\Http\Controllers;

use Ahinest\LaravelPrintKit\Data\PrintFieldResolver;
use Ahinest\LaravelPrintKit\Data\Registries\CategoryRegistry;
use Ahinest\LaravelPrintKit\Data\Registries\DriverRegistry;
use Ahinest\LaravelPrintKit\Data\Registries\SystemRegistry;
use Ahinest\LaravelPrintKit\Http\Requests\ApiInput;
use Ahinest\LaravelPrintKit\Http\Resources\FieldOptionResource;
use Ahinest\LaravelPrintKit\Http\Resources\OptionResponse;
use Ahinest\LaravelPrintKit\Http\Resources\PaperSizeOptionResource;
use Ahinest\LaravelPrintKit\Http\Resources\RegistryOptionResource;
use Ahinest\LaravelPrintKit\Support\ModelResolver;
use Illuminate\Http\Request;

/** Publica proyecciones de catálogo apropiadas para el diseñador, sin metadatos de almacenamiento. */
final class OptionsController extends ApiController
{
    public function drivers(Request $request, DriverRegistry $registry): mixed
    {
        return OptionResponse::make($registry->all(), RegistryOptionResource::class, $request);
    }

    public function systems(Request $request, SystemRegistry $registry): mixed
    {
        return OptionResponse::make($registry->all(), RegistryOptionResource::class, $request);
    }

    public function categories(Request $request, CategoryRegistry $registry): mixed
    {
        return OptionResponse::make($registry->all(), RegistryOptionResource::class, $request);
    }

    public function papers(Request $request, ApiInput $input, ModelResolver $models): mixed
    {
        $filters = $input->query($request, 'papers');
        $query = $models->make('paper_size')->newQuery();
        if (! ($filters['include_inactive'] ?? false)) {
            $query->where('is_active', true);
        }

        return OptionResponse::make($query->get()->all(), PaperSizeOptionResource::class, $request);
    }

    public function fields(Request $request, ApiInput $input, PrintFieldResolver $fields): mixed
    {
        $filters = $input->query($request, 'fields');
        $items = $fields->fieldsFor($filters['driver'], $filters['system'], $filters['category']);

        return OptionResponse::make(array_filter($items, fn ($field) => (bool) $field->getAttribute('usable_in_detail')), FieldOptionResource::class, $request);
    }
}
