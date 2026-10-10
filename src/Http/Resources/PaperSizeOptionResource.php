<?php

declare(strict_types=1);

namespace Ahinest\LaravelPrintKit\Http\Resources;

use Ahinest\LaravelPrintKit\Design\Decimal;
use Illuminate\Http\Resources\Json\JsonResource;

/** Proyecta únicamente datos físicos y descriptivos útiles al selector de papeles. */
class PaperSizeOptionResource extends JsonResource
{
    public function toArray($request): array
    {
        return ['value' => $this->resource->getAttribute('id'), 'label' => $this->resource->getAttribute('name'), 'description' => $this->resource->getAttribute('description'),
            'width' => (string) Decimal::from($this->resource->getAttribute('width'), 'paper.width', true), 'height' => (string) Decimal::from($this->resource->getAttribute('height'), 'paper.height', true)];
    }
}
