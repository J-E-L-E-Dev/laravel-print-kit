<?php

declare(strict_types=1);

namespace Ahinest\LaravelPrintKit\Http\Requests;

use Ahinest\LaravelPrintKit\Data\ResolvedValue;
use Illuminate\Contracts\Validation\Factory;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\UnsupportedMediaTypeHttpException;

/** Valida listas explícitas de entrada; lee JSON original para preservar valores finales sin trims globales. */
final class ApiInput
{
    public const EDITABLE = ['code', 'name', 'copies', 'preview', 'printer', 'paper_size_id', 'orientation', 'system', 'category', 'detail', 'margins', 'use_default_printer', 'disabled', 'parameters'];

    public function __construct(private Factory $validation) {}

    private function formatRules(string $mode): array
    {
        return [
            'code' => [$mode === 'store' ? 'required' : 'sometimes', 'required', 'string', 'max:100'],
            'name' => [$mode === 'store' ? 'required' : 'sometimes', 'required', 'string', 'max:255'],
            'copies' => ['sometimes', 'filled', 'integer', 'min:1', 'max:2147483647'],
            'preview' => ['sometimes', 'filled', 'boolean'], 'printer' => ['sometimes', 'nullable', 'string', 'max:255'],
            'paper_size_id' => [$mode === 'update' ? 'sometimes' : 'required', 'filled', 'integer', 'min:1'],
            'orientation' => ['sometimes', 'filled', 'in:V,H'], 'system' => ['sometimes', 'filled', 'string', 'regex:/^[A-Z][A-Z0-9]{2}$/D'],
            'category' => ['sometimes', 'filled', 'in:ET'],
            'detail' => [$mode === 'draft' ? 'required' : 'sometimes', 'array'],
            'margins' => ['sometimes', 'array:top,right,bottom,left'],
            'margins.top' => ['required_with:margins', 'numeric', 'min:0'],
            'margins.right' => ['required_with:margins', 'numeric', 'min:0'],
            'margins.bottom' => ['required_with:margins', 'numeric', 'min:0'],
            'margins.left' => ['required_with:margins', 'numeric', 'min:0'],
            'use_default_printer' => ['sometimes', 'filled', 'boolean'], 'disabled' => ['sometimes', 'filled', 'boolean'],
            'parameters' => ['sometimes', 'nullable', 'array'],
        ];
    }

    public function body(Request $request, string $mode): array
    {
        if (! $request->isJson()) {
            throw new UnsupportedMediaTypeHttpException('Se requiere application/json.');
        }
        try {
            $object = json_decode($request->getContent(), false, 512, JSON_THROW_ON_ERROR);
            $data = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw ValidationException::withMessages(['body' => ['JSON inválido.']]);
        }
        if (! $object instanceof \stdClass) {
            throw ValidationException::withMessages(['body' => ['Se requiere un objeto JSON.']]);
        }
        $valueRules = ['values' => ['present', 'array'], 'values.*' => ['nullable', function ($attribute, $value, $fail) {
            try {
                ResolvedValue::validate($value);
            } catch (\InvalidArgumentException) {
                $fail('Se requiere un escalar finito, texto UTF-8 o NULL.');
            }
        }]];
        if ($mode === 'preview') {
            $rules = ['format' => ['required', 'array']] + $valueRules;
            foreach ($this->formatRules('draft') as $key => $rule) {
                $rules['format.'.$key] = array_map(fn ($item) => $item === 'required_with:margins' ? 'required_with:format.margins' : $item, $rule);
            }
            $this->unknown($data, ['format', 'values']);
            if (is_array($data['format'] ?? null)) {
                $this->unknown($data['format'], self::EDITABLE, 'format.');
            }
        } elseif ($mode === 'stored-preview') {
            $rules = $valueRules;
            $this->unknown($data, ['values']);
        } else {
            $rules = $this->formatRules($mode);
            $this->unknown($data, self::EDITABLE);
        }

        return $this->validation->make($data, $rules)->validate();
    }

    public function query(Request $request, string $mode): array
    {
        $rules = match ($mode) {
            'index' => ['system' => ['sometimes', 'string'], 'category' => ['sometimes', 'in:ET'], 'disabled' => ['sometimes', 'boolean'],
                'search' => ['sometimes', 'string', 'max:100'], 'page' => ['sometimes', 'integer', 'min:1'],
                'per_page' => ['sometimes', 'integer', 'min:1', 'max:'.(int) config('print-kit.api.max_per_page', 100)]],
            'fields' => ['driver' => ['required', 'string', 'max:100'], 'system' => ['required', 'string'], 'category' => ['required', 'in:ET']],
            'papers' => ['include_inactive' => ['sometimes', 'boolean']],
        };
        $data = [];
        foreach (array_keys($rules) as $key) {
            if ($request->query->has($key)) {
                $data[$key] = $request->query($key);
            }
        }

        return $this->validation->make($data, $rules)->validate();
    }

    private function unknown(array $data, array $allowed, string $prefix = ''): void
    {
        $errors = [];
        foreach (array_diff(array_keys($data), $allowed) as $key) {
            $errors[$prefix.$key] = ['Propiedad no admitida.'];
        }
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }
}
