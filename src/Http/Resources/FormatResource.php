<?php

declare(strict_types=1);

namespace Ahinest\LaravelPrintKit\Http\Resources;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Resources\Json\JsonResource;

/** Expone solo atributos canónicos; el listado omite detail y relaciones. */
class FormatResource extends JsonResource
{
    public const SUMMARY = ['id', 'code', 'name', 'copies', 'preview', 'printer', 'paper_size_id', 'orientation', 'system', 'category', 'version', 'margins', 'use_default_printer', 'disabled', 'parameters', 'created_at', 'updated_at'];

    private ?Model $paper = null;

    public function withPaper(Model $paper): self
    {
        $this->paper = $paper;

        return $this;
    }

    public function toArray($request): array
    {
        $serialized = $this->resource->attributesToArray();
        $data = [];
        foreach (self::SUMMARY as $key) {
            $data[$key] = $serialized[$key] ?? null;
        }
        foreach (['preview', 'use_default_printer', 'disabled'] as $key) {
            $data[$key] = (bool) $data[$key];
        }
        foreach (['copies', 'version'] as $key) {
            $data[$key] = (int) $data[$key];
        }
        if ($this->paper !== null) {
            $data['detail'] = $this->resource->getAttribute('detail');
            $option = (new PaperSizeOptionResource($this->paper))->toArray($request);
            $data['paper_size'] = ['id' => $option['value'], 'name' => $option['label'], 'description' => $option['description'], 'width' => $option['width'], 'height' => $option['height']];
            $data['designer'] = ['schema_version' => 1, 'geometry_unit' => 'cm', 'font_size_unit' => 'pt'];
        }

        return $data;
    }
}
