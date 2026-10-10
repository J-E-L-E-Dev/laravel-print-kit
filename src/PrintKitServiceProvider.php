<?php

declare(strict_types=1);

namespace Ahinest\LaravelPrintKit;

use Ahinest\LaravelPrintKit\Data\FieldTransformerRegistry;
use Ahinest\LaravelPrintKit\Support\ColumnResolver;
use Ahinest\LaravelPrintKit\Support\ModelResolver;
use Illuminate\Support\ServiceProvider;

/** Registra la infraestructura sin ejecutar migraciones automáticamente. */
class PrintKitServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $defaults = require __DIR__.'/../config/print-kit.php';
        $configured = $this->app['config']->get('print-kit', []);
        $merged = array_replace_recursive($defaults, $configured);
        $merged['api'] = array_replace($defaults['api'], $configured['api'] ?? []);
        // Las listas permitidas explícitas reemplazan defaults; no se amplían silenciosamente.
        foreach (['drivers', 'systems', 'categories', 'transformers'] as $registry) {
            if (array_key_exists($registry, $configured)) {
                $merged[$registry] = $configured[$registry];
            }
        }
        $this->app['config']->set('print-kit', $merged);
        $this->app->singleton(ColumnResolver::class);
        $this->app->singleton(ModelResolver::class);
        $this->app->singleton(FieldTransformerRegistry::class);
    }

    public function boot(): void
    {
        if ($this->app['config']->get('print-kit.api.enabled') === true) {
            $this->loadRoutesFrom(__DIR__.'/../routes/api.php');
        }
        if ($this->app->runningInConsole()) {
            $this->publishes([__DIR__.'/../config/print-kit.php' => config_path('print-kit.php')], 'print-kit-config');
            $this->publishes([__DIR__.'/../database/migrations' => database_path('migrations')], 'print-kit-migrations');
        }
    }
}
