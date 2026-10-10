<?php

declare(strict_types=1);

namespace Ahinest\LaravelPrintKit\Tests;

/** Configuración explícita del escenario de inventario; no son defaults del paquete. */
abstract class ConfiguredTestCase extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('print-kit.drivers', [
            'mysql' => ['label' => 'MySQL principal', 'description' => 'Base de datos principal.'],
            'mssql' => ['label' => 'SQL Server', 'description' => 'Base administrativa.'],
        ]);
        $app['config']->set('print-kit.systems', [
            'INV' => ['label' => 'Inventario', 'description' => 'Productos y existencias.', 'table' => 'I00001', 'key' => 'producto', 'categories' => ['ET']],
        ]);
        $app['config']->set('print-kit.categories', [
            'ET' => ['label' => 'Etiquetas', 'description' => 'Formatos de impresión de etiquetas.'],
        ]);
        $app['config']->set('print-kit.defaults.fields.driver', 'mysql');
        $app['config']->set('print-kit.defaults.fields.system', 'INV');
        $app['config']->set('print-kit.defaults.formats.system', 'INV');
    }
}
