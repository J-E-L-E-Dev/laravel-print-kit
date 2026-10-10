<?php

declare(strict_types=1);

namespace Ahinest\LaravelPrintKit\Http\Middleware;

use Ahinest\LaravelPrintKit\Data\Exceptions\DuplicatePrintFieldDefinition;
use Ahinest\LaravelPrintKit\Data\Exceptions\InvalidDataType;
use Ahinest\LaravelPrintKit\Data\Exceptions\InvalidResolvedValue;
use Ahinest\LaravelPrintKit\Data\Exceptions\MissingProvidedValue;
use Ahinest\LaravelPrintKit\Data\Exceptions\PrintFieldNotFound;
use Ahinest\LaravelPrintKit\Data\Exceptions\TransformerNotFound;
use Ahinest\LaravelPrintKit\Data\Exceptions\UnknownCategory;
use Ahinest\LaravelPrintKit\Data\Exceptions\UnknownDriver;
use Ahinest\LaravelPrintKit\Data\Exceptions\UnknownSystem;
use Ahinest\LaravelPrintKit\Design\InvalidDesign;
use Ahinest\LaravelPrintKit\Rendering\Exceptions\IncompatibleRenderedLabels;
use Ahinest\LaravelPrintKit\Rendering\Exceptions\InvalidRenderContext;
use Ahinest\LaravelPrintKit\Rendering\Exceptions\QrCodeGenerationFailed;
use Ahinest\LaravelPrintKit\Rendering\Exceptions\UnsupportedRenderCategory;
use Ahinest\LaravelPrintKit\Support\InvalidPaperSize;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

/** Traduce errores solo en rutas del paquete y conserva JSON incluso cuando preview solicita HTML. */
final class ApiErrors
{
    public function handle(Request $request, \Closure $next): mixed
    {
        if (config('print-kit.api.enabled') !== true) {
            return new JsonResponse(['message' => 'API no disponible.'], 404);
        }
        try {
            return $next($request);
        } catch (ValidationException $exception) {
            return $this->invalid($exception->errors());
        } catch (InvalidPaperSize) {
            return new JsonResponse(['message' => 'Papel inexistente o incompatible.'], 404);
        } catch (InvalidDesign $exception) {
            $prefix = $request->route()?->getName() === 'print-kit.preview' ? 'format.' : '';

            return $this->invalid([$prefix.$exception->path => [$exception->getMessage()]]);
        } catch (UnknownDriver|UnknownSystem|UnknownCategory $exception) {
            $key = match (true) {
                $exception instanceof UnknownDriver => 'driver',$exception instanceof UnknownSystem => 'system',default => 'category'
            };
            if ($request->route()?->getName() === 'print-kit.preview') {
                $key = 'format.'.$key;
            }

            return $this->invalid([$key => [$exception->getMessage()]]);
        } catch (MissingProvidedValue $exception) {
            return $this->invalid(['values'.($exception->placeholder === null ? '' : '.'.$exception->placeholder) => [$exception->getMessage()]]);
        } catch (QrCodeGenerationFailed) {
            return $this->invalid(['format.fields' => ['No fue posible generar uno de los códigos QR del formato.']]);
        } catch (InvalidResolvedValue|PrintFieldNotFound|InvalidDataType|DuplicatePrintFieldDefinition|TransformerNotFound|InvalidRenderContext|UnsupportedRenderCategory|IncompatibleRenderedLabels $exception) {
            return $this->invalid(['design' => ['No se puede validar o renderizar el diseño con los datos proporcionados.']]);
        } catch (HttpExceptionInterface $exception) {
            return new JsonResponse(['message' => match ($exception->getStatusCode()) {
                401 => 'Autenticación requerida.',403 => 'Acceso denegado.',404 => 'Recurso inexistente.',409 => 'El tamaño de papel está siendo utilizado por uno o más formatos y no puede eliminarse.',415 => 'Se requiere application/json.',default => 'No se pudo procesar la solicitud.'
            }], $exception->getStatusCode(), $exception->getHeaders());
        } catch (\Throwable $exception) {
            report($exception);

            return new JsonResponse(['message' => 'No se pudo completar la operación.'], 500);
        }
    }

    private function invalid(array $errors): JsonResponse
    {
        return new JsonResponse(['message' => 'Los datos proporcionados no son válidos.', 'errors' => $errors], 422);
    }
}
