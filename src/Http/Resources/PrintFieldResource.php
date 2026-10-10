<?php

declare(strict_types=1);

namespace Ahinest\LaravelPrintKit\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/** Proyección administrativa explícita; no expone atributos físicos del almacenamiento. */
final class PrintFieldResource extends JsonResource
{
    public function toArray($request): array
    {
        $data = array_intersect_key($this->resource->attributesToArray(), array_flip(['id', 'driver', 'system', 'category', 'placeholder', 'display_name', 'description', 'data_type', 'usable_in_detail', 'associated_field', 'created_at', 'updated_at']));

        $data['usable_in_detail'] = (bool) $this->resource->getAttribute('usable_in_detail');
        $data['description'] = $this->resource->getAttribute('description');

        return $data;
    }
}
