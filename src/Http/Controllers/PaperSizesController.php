<?php

declare(strict_types=1);

namespace Ahinest\LaravelPrintKit\Http\Controllers;

use Ahinest\LaravelPrintKit\Http\PaperSizeManager;
use Ahinest\LaravelPrintKit\Http\Requests\PaperSizeIndexRequest;
use Ahinest\LaravelPrintKit\Http\Requests\StorePaperSizeRequest;
use Ahinest\LaravelPrintKit\Http\Requests\UpdatePaperSizeRequest;
use Ahinest\LaravelPrintKit\Http\Resources\PaperSizeResource;
use Illuminate\Http\Response;

/** Delega validación HTTP, persistencia y serialización a sus colaboradores. */
final class PaperSizesController extends ApiController
{
    public function __construct(private PaperSizeManager $manager) {}

    public function index(PaperSizeIndexRequest $request): mixed
    {
        return PaperSizeResource::collection($this->manager->list($request->validated()));
    }

    public function store(StorePaperSizeRequest $request): mixed
    {
        return PaperSizeResource::make($this->manager->create($request->validated()))->response()->setStatusCode(201);
    }

    public function show(string $paperSize): PaperSizeResource
    {
        return PaperSizeResource::make($this->manager->find($paperSize));
    }

    public function update(UpdatePaperSizeRequest $request, string $paperSize): PaperSizeResource
    {
        return PaperSizeResource::make($this->manager->update($this->manager->find($paperSize), $request->validated()));
    }

    public function destroy(string $paperSize): Response
    {
        $this->manager->delete($this->manager->find($paperSize));

        return new Response('', 204);
    }
}
