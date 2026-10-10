<?php

declare(strict_types=1);

namespace Ahinest\LaravelPrintKit\Tests;

use Ahinest\LaravelPrintKit\PrintKitServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

/** Ejecuta las pruebas en SQLite aislado, sin conexiones de la aplicación consumidora. */
abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [PrintKitServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true,
        ]);
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }
}
