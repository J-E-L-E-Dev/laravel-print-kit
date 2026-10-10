<?php

declare(strict_types=1);

use Ahinest\LaravelPrintKit\Models\PaperSize;
use Ahinest\LaravelPrintKit\Models\PrintField;
use Ahinest\LaravelPrintKit\Models\PrintFormat;

return [
    'api' => [
        'enabled' => false,
        'prefix' => 'print-kit',
        'middleware' => ['api'],
        'per_page' => 25,
        'max_per_page' => 100,
    ],
    // Registrar las conexiones, sistemas y categorías de la aplicación consumidora.
    'drivers' => [],
    'systems' => [],
    'categories' => [],
    'transformers' => ['drivers' => [], 'systems' => []],
    'database' => ['connection' => null],
    'models' => [
        'field' => PrintField::class,
        'format' => PrintFormat::class,
        'paper_size' => PaperSize::class,
    ],
    'tables' => [
        'fields' => 'print_fields',
        'formats' => 'print_formats',
        'paper_sizes' => 'print_paper_sizes',
    ],
    // Los mapas parciales conservan los nombres canónicos no especificados.
    'columns' => [
        'fields' => array_combine(
            $fields = ['id', 'driver', 'system', 'category', 'placeholder', 'display_name', 'description', 'data_type', 'usable_in_detail', 'associated_field', 'created_at', 'updated_at'],
            $fields,
        ),
        'formats' => array_combine(
            $formats = ['id', 'code', 'name', 'copies', 'preview', 'printer', 'paper_size_id', 'orientation', 'system', 'detail', 'category', 'version', 'margins', 'use_default_printer', 'disabled', 'parameters', 'created_at', 'updated_at'],
            $formats,
        ),
        'paper_sizes' => array_combine(
            $sizes = ['id', 'name', 'description', 'width', 'height', 'is_active', 'created_at', 'updated_at'],
            $sizes,
        ),
    ],
    'defaults' => [
        'fields' => ['category' => 'ET', 'data_type' => 'C', 'usable_in_detail' => false],
        'formats' => [
            'copies' => 1, 'preview' => true, 'orientation' => 'V',
            'category' => 'ET', 'version' => 1, 'detail' => ['schema_version' => 1, 'elements' => []],
            'margins' => ['top' => 0, 'right' => 0, 'bottom' => 0, 'left' => 0],
            'use_default_printer' => false, 'disabled' => false, 'parameters' => null,
        ],
        'paper_sizes' => ['is_active' => true],
    ],
];
