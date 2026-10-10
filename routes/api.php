<?php

declare(strict_types=1);
use Ahinest\LaravelPrintKit\Http\Controllers\DesignController;
use Ahinest\LaravelPrintKit\Http\Controllers\FormatsController;
use Ahinest\LaravelPrintKit\Http\Controllers\OptionsController;
use Ahinest\LaravelPrintKit\Http\Controllers\PaperSizesController;
use Ahinest\LaravelPrintKit\Http\Controllers\PrintFieldsController;
use Ahinest\LaravelPrintKit\Http\Middleware\ApiErrors;
use Illuminate\Support\Facades\Route;

Route::prefix(config('print-kit.api.prefix', 'print-kit'))
    ->middleware(array_merge([ApiErrors::class], config('print-kit.api.middleware', ['api'])))
    ->name('print-kit.')->group(function (): void {
        Route::get('paper-sizes', [PaperSizesController::class, 'index'])->name('paper-sizes.index');
        Route::post('paper-sizes', [PaperSizesController::class, 'store'])->name('paper-sizes.store');
        Route::get('paper-sizes/{paperSize}', [PaperSizesController::class, 'show'])->name('paper-sizes.show');
        Route::put('paper-sizes/{paperSize}', [PaperSizesController::class, 'update'])->name('paper-sizes.update');
        Route::delete('paper-sizes/{paperSize}', [PaperSizesController::class, 'destroy'])->name('paper-sizes.destroy');
        Route::get('fields', [PrintFieldsController::class, 'index'])->name('fields.index');
        Route::post('fields', [PrintFieldsController::class, 'store'])->name('fields.store');
        Route::get('fields/{field}', [PrintFieldsController::class, 'show'])->name('fields.show');
        Route::put('fields/{field}', [PrintFieldsController::class, 'update'])->name('fields.update');
        Route::get('formats', [FormatsController::class, 'index'])->name('formats.index');
        Route::post('formats', [FormatsController::class, 'store'])->name('formats.store');
        Route::get('formats/{format}', [FormatsController::class, 'show'])->name('formats.show');
        Route::put('formats/{format}', [FormatsController::class, 'update'])->name('formats.update');
        Route::delete('formats/{format}', [FormatsController::class, 'destroy'])->name('formats.destroy');
        Route::get('options/drivers', [OptionsController::class, 'drivers'])->name('options.drivers');
        Route::get('options/systems', [OptionsController::class, 'systems'])->name('options.systems');
        Route::get('options/categories', [OptionsController::class, 'categories'])->name('options.categories');
        Route::get('options/paper-sizes', [OptionsController::class, 'papers'])->name('options.paper-sizes');
        Route::get('options/fields', [OptionsController::class, 'fields'])->name('options.fields');
        Route::post('design/validate', [DesignController::class, 'validate'])->name('design.validate');
        Route::post('preview', [DesignController::class, 'preview'])->name('preview');
        Route::post('formats/{format}/preview', [DesignController::class, 'storedPreview'])->name('formats.preview');
    });
