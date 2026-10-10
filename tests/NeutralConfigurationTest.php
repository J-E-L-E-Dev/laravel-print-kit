<?php

declare(strict_types=1);

namespace Ahinest\LaravelPrintKit\Tests;

use Ahinest\LaravelPrintKit\Data\Registries\CategoryRegistry;
use Ahinest\LaravelPrintKit\Data\Registries\DriverRegistry;
use Ahinest\LaravelPrintKit\Data\Registries\SystemRegistry;
use Ahinest\LaravelPrintKit\Models\PrintField;
use Ahinest\LaravelPrintKit\Models\PrintFormat;
use Ahinest\LaravelPrintKit\PrintKitServiceProvider;

/** Comprueba la instalación sin configurar un origen ni cargar fixtures de inventario. */
class NeutralConfigurationTest extends TestCase
{
    public function test_unconfigured_package_boots_with_empty_registries_and_no_routes(): void
    {
        $this->assertNotNull($this->app->getProvider(PrintKitServiceProvider::class));
        foreach ([DriverRegistry::class, SystemRegistry::class, CategoryRegistry::class] as $registry) {
            $this->assertSame([], $this->app->make($registry)->all());
        }
        $this->assertFalse(config('print-kit.api.enabled'));
        $this->assertNull((new PrintField)->driver);
        $this->assertNull((new PrintField)->system);
        $this->assertNull((new PrintFormat)->system);
        $this->assertFalse(app('router')->has('print-kit.formats.index'));
        $this->getJson('/print-kit/formats')->assertNotFound();
    }

    public function test_enabled_options_return_empty_lists_without_consumer_configuration(): void
    {
        config(['print-kit.api.enabled' => true]);
        (new PrintKitServiceProvider($this->app))->boot();
        foreach (['drivers', 'systems', 'categories', 'paper-sizes'] as $option) {
            $this->getJson('/print-kit/options/'.$option)->assertOk()->assertExactJson(['data' => []]);
        }
        // Fields necesita un contexto registrado; un contexto desconocido es validación, no error 500.
        $this->getJson('/print-kit/options/fields?driver=inventory&system=INV&category=ET')->assertUnprocessable();
    }
}
