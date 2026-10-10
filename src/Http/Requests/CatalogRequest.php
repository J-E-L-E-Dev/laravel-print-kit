<?php

declare(strict_types=1);

namespace Ahinest\LaravelPrintKit\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\UnsupportedMediaTypeHttpException;

/** Conserva el JSON original y limita atributos; la autorización pertenece al middleware consumidor. */
abstract class CatalogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function validationData(): array
    {
        if ($this->isMethod('GET')) {
            return array_intersect_key($this->query->all(), $this->rules());
        }
        if (! $this->isJson()) {
            throw new UnsupportedMediaTypeHttpException;
        }
        try {
            $object = json_decode($this->getContent(), false, 512, JSON_THROW_ON_ERROR);
            $data = json_decode($this->getContent(), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw ValidationException::withMessages(['body' => ['JSON inválido.']]);
        }
        if (! $object instanceof \stdClass) {
            throw ValidationException::withMessages(['body' => ['Se requiere un objeto JSON.']]);
        }
        $unknown = array_diff(array_keys($data), array_keys($this->rules()));
        if ($unknown !== []) {
            throw ValidationException::withMessages(array_fill_keys($unknown, ['Propiedad no admitida.']));
        }

        return $data;
    }
}
