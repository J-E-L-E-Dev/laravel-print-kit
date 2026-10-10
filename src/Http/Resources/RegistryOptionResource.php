<?php

declare(strict_types=1);

namespace Ahinest\LaravelPrintKit\Http\Resources;

use Ahinest\LaravelPrintKit\Data\Registries\SystemDefinition;
use Illuminate\Http\Resources\Json\JsonResource;

/** Oculta tabla y clave físicas del registro de sistemas. */
class RegistryOptionResource extends JsonResource
{
    public function toArray($request): array
    {
        $entry = $this->resource;
        $data = ['value' => $entry->code, 'label' => $entry->label, 'description' => $entry->description];
        if ($entry instanceof SystemDefinition) {
            $data['categories'] = $entry->categories;
        }

        return $data;
    }
}
