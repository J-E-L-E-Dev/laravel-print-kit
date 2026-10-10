<?php

declare(strict_types=1);

namespace Ahinest\LaravelPrintKit\Tests\Data;

use Ahinest\LaravelPrintKit\Data\Exceptions\InvalidRegistryConfiguration;
use Ahinest\LaravelPrintKit\Data\Exceptions\UnknownCategory;
use Ahinest\LaravelPrintKit\Data\Exceptions\UnknownDriver;
use Ahinest\LaravelPrintKit\Data\Exceptions\UnknownSystem;
use Ahinest\LaravelPrintKit\Data\Registries\CategoryRegistry;
use Ahinest\LaravelPrintKit\Data\Registries\DriverRegistry;
use Ahinest\LaravelPrintKit\Data\Registries\SystemRegistry;
use Ahinest\LaravelPrintKit\Tests\ConfiguredTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

class RegistryTest extends ConfiguredTestCase
{
    public function test_metadata_and_driver_independent_system(): void
    {
        $driver = app(DriverRegistry::class)->get('mysql');
        $this->assertSame(['value' => 'mysql', 'label' => 'MySQL principal', 'description' => 'Base de datos principal.'], $driver->toArray());
        $system = app(SystemRegistry::class)->get('INV');
        $this->assertSame('I00001', $system->table);
        $this->assertSame('producto', $system->key);
        $this->assertSame(['ET'], $system->categories);
        $this->assertSame('ET', app(CategoryRegistry::class)->get('ET')->toArray()['value']);
        $this->assertCount(2, app(DriverRegistry::class)->all());
        $this->assertSame($system->toArray(), json_decode(json_encode($system), true));
    }

    #[DataProvider('unknown')]
    public function test_unknown(string $registry, string $exception): void
    {
        $this->expectException($exception);
        app($registry)->get('missing');
    }

    public static function unknown(): array
    {
        return [[DriverRegistry::class, UnknownDriver::class], [SystemRegistry::class, UnknownSystem::class], [CategoryRegistry::class, UnknownCategory::class]];
    }

    #[DataProvider('malformed')]
    public function test_invalid_configuration(string $path, mixed $value, string $registry): void
    {
        config(["print-kit.$path" => $value]);
        $this->expectException(InvalidRegistryConfiguration::class);
        app($registry)->all();
    }

    public static function malformed(): array
    {
        return [
            ['drivers', false, DriverRegistry::class], ['drivers.*', ['label' => 'x', 'description' => 'x'], DriverRegistry::class],
            ['drivers.mysql.label', ' ', DriverRegistry::class], ['drivers.mysql.description', null, DriverRegistry::class],
            ['systems.INV.table', 'x; drop table y', SystemRegistry::class], ['systems.INV.table', 'dbo.I00001', SystemRegistry::class],
            ['systems.INV.key', 'id or 1=1', SystemRegistry::class], ['systems.INV.driver', 'mysql', SystemRegistry::class],
            ['systems.INV.categories', ['XX'], SystemRegistry::class], ['systems.INV.categories', ['*'], SystemRegistry::class],
            ['systems.INV.categories', [], SystemRegistry::class], ['systems.INV.categories', 'ET', SystemRegistry::class],
            ['systems.*', ['label' => 'x', 'description' => 'x'], SystemRegistry::class],
            ['categories.*', ['label' => 'x', 'description' => 'x'], CategoryRegistry::class],
            ['categories.ET.label', '', CategoryRegistry::class],
        ];
    }
}
