<?php

declare(strict_types=1);

namespace Ahinest\LaravelPrintKit\Tests\Http;

use Ahinest\LaravelPrintKit\Models\PaperSize;
use Ahinest\LaravelPrintKit\Models\PrintField;
use Ahinest\LaravelPrintKit\Tests\ConfiguredTestCase;
use Illuminate\Support\Facades\DB;

/** Prepara la API habilitada sobre el almacenamiento aislado de pruebas. */
abstract class ApiTestCase extends ConfiguredTestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('print-kit.api.enabled', true);
    }

    protected function draft(bool $field = true): array
    {
        $paper = PaperSize::create(['name' => 'Etiqueta', 'width' => 5, 'height' => 3]);
        if ($field) {
            PrintField::create(['placeholder' => 'DESCRIPTION', 'display_name' => 'Descripción', 'associated_field' => 'descripcio', 'usable_in_detail' => true]);
        }

        return ['paper_size_id' => $paper->id, 'orientation' => 'V', 'system' => 'INV', 'category' => 'ET', 'margins' => ['top' => '0.2', 'right' => '0.2', 'bottom' => '0.2', 'left' => '0.2'],
            'detail' => ['schema_version' => 1, 'elements' => [$field
                ? ['id' => 'a', 'type' => 'field', 'field' => 'DESCRIPTION', 'x' => '0.2', 'y' => '0.3', 'width' => 2, 'height' => '0.5']
                : ['id' => 'a', 'type' => 'text', 'text' => '<b>ñ</b>', 'x' => '0.2', 'y' => '0.3', 'width' => 2, 'height' => '0.5']]]];
    }

    protected function payload(bool $field = false): array
    {
        return ['code' => 'LABEL', 'name' => 'Etiqueta'] + $this->draft($field);
    }

    protected function watch(): void
    {
        DB::connection()->flushQueryLog();
        DB::connection()->enableQueryLog();
    }
}
