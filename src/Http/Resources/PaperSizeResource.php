<?php

declare(strict_types=1);

namespace Ahinest\LaravelPrintKit\Http\Resources;

use Ahinest\LaravelPrintKit\Design\Decimal;
use Illuminate\Http\Resources\Json\JsonResource;

/** Proyección administrativa explícita; no expone atributos físicos del almacenamiento. */
final class PaperSizeResource extends JsonResource
{
    public function toArray($request): array
    {
        $data = array_intersect_key($this->resource->attributesToArray(), array_flip(['id', 'name', 'description', 'width', 'height', 'is_active', 'created_at', 'updated_at']));
        foreach (['width', 'height'] as $key) {
            $data[$key] = (string) Decimal::from($this->resource->getAttribute($key), $key, true);
        }
        $data['is_active'] = (bool) $this->resource->getAttribute('is_active');
        $data['description'] = $this->resource->getAttribute('description');

        return $data;
    }
}
