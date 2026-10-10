<?php

declare(strict_types=1);

namespace Ahinest\LaravelPrintKit\Http\Controllers;

use Ahinest\LaravelPrintKit\Http\FormatManager;
use Ahinest\LaravelPrintKit\Http\Requests\ApiInput;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/** Valida borradores y genera un único documento de preview sin guardar ni consultar el origen. */
final class DesignController extends ApiController
{
    public function __construct(private ApiInput $input, private FormatManager $formats) {}

    public function validate(Request $request): JsonResponse
    {
        $format = $this->formats->temporary($this->input->body($request, 'draft'));
        $this->formats->validate($format);

        return new JsonResponse(['valid' => true]);
    }

    public function preview(Request $request): Response
    {
        $data = $this->input->body($request, 'preview');
        $format = $this->formats->temporary($data['format']);

        return new Response($this->formats->preview($format, $data['values'])->html(), 200, ['Content-Type' => 'text/html; charset=UTF-8']);
    }

    public function storedPreview(Request $request, string $format): Response
    {
        $model = $this->formats->find($format);
        $data = $this->input->body($request, 'stored-preview');

        return new Response($this->formats->preview($model, $data['values'])->html(), 200, ['Content-Type' => 'text/html; charset=UTF-8']);
    }
}
