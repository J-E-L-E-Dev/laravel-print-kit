<?php

declare(strict_types=1);

namespace Ahinest\LaravelPrintKit\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/** Expone placeholders canónicos, sin associated_field ni datos físicos del origen. */
class FieldOptionResource extends JsonResource
{
    public function toArray($request): array
    {
        return ['value' => $this->resource->getAttribute('placeholder'), 'label' => $this->resource->getAttribute('display_name'),
            'description' => $this->resource->getAttribute('description'), 'data_type' => $this->resource->getAttribute('data_type')];
    }
}
