<?php

declare(strict_types=1);

namespace Ahinest\LaravelPrintKit\Http\Controllers;

use Ahinest\LaravelPrintKit\Http\PrintFieldManager;
use Ahinest\LaravelPrintKit\Http\Requests\PrintFieldIndexRequest;
use Ahinest\LaravelPrintKit\Http\Requests\StorePrintFieldRequest;
use Ahinest\LaravelPrintKit\Http\Requests\UpdatePrintFieldRequest;
use Ahinest\LaravelPrintKit\Http\Resources\PrintFieldResource;

/** Delega validación HTTP, persistencia y serialización a sus colaboradores. */
final class PrintFieldsController extends ApiController
{
    public function __construct(private PrintFieldManager $manager) {}

    public function index(PrintFieldIndexRequest $request): mixed
    {
        return PrintFieldResource::collection($this->manager->list($request->validated()));
    }

    public function store(StorePrintFieldRequest $request): mixed
    {
        return PrintFieldResource::make($this->manager->create($request->validated()))->response()->setStatusCode(201);
    }

    public function show(string $field): PrintFieldResource
    {
        return PrintFieldResource::make($this->manager->find($field));
    }

    public function update(UpdatePrintFieldRequest $request, string $field): PrintFieldResource
    {
        return PrintFieldResource::make($this->manager->update($this->manager->find($field), $request->validated()));
    }
}
