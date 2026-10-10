<?php

declare(strict_types=1);

namespace Ahinest\LaravelPrintKit\Tests\Data;

use Ahinest\LaravelPrintKit\Data\Exceptions\InvalidRegistryConfiguration;
use Ahinest\LaravelPrintKit\Data\Exceptions\UnknownCategory;
use Ahinest\LaravelPrintKit\Data\Exceptions\UnknownDriver;
use Ahinest\LaravelPrintKit\Data\Exceptions\UnknownSystem;
use Ahinest\LaravelPrintKit\Data\FieldTransformerRegistry;
use Ahinest\LaravelPrintKit\Data\Registries\DriverRegistry;
use Ahinest\LaravelPrintKit\PrintKitServiceProvider;
use Ahinest\LaravelPrintKit\Tests\ConfiguredTestCase;
use Ahinest\LaravelPrintKit\Tests\Fixtures\PrefixTransformer;
use PHPUnit\Framework\Attributes\DataProvider;

class TransformerRegistryTest extends ConfiguredTestCase
{
    public function test_all_four_precedence_levels_and_programmatic_override(): void
    {
        $registry = app(FieldTransformerRegistry::class);
        $genericWildcard = new PrefixTransformer('generic wildcard');
        $genericExact = new PrefixTransformer('generic exact');
        $driverWildcard = new PrefixTransformer('driver wildcard');
        $driverExact = new PrefixTransformer('driver exact');
        $this->assertNull($registry->get('mysql', 'INV', 'ET', 'PRICE'));
        $registry->register('INV', '*', 'PRICE', $genericWildcard);
        $this->assertSame($genericWildcard, $registry->get('mysql', 'INV', 'ET', 'PRICE'));
        $registry->register('INV', 'ET', 'PRICE', $genericExact);
        $this->assertSame($genericExact, $registry->get('mysql', 'INV', 'ET', 'PRICE'));
        $registry->register('INV', '*', 'PRICE', $driverWildcard, 'mysql');
        $this->assertSame($driverWildcard, $registry->get('mysql', 'INV', 'ET', 'PRICE'));
        $registry->register('INV', 'ET', 'PRICE', $driverExact, 'mysql');
        $this->assertSame($driverExact, $registry->get('mysql', 'INV', 'ET', 'PRICE'));
        $this->assertSame($genericExact, $registry->get('mssql', 'INV', 'ET', 'PRICE'));
        config(['print-kit.transformers.drivers.mysql.INV.ET.PRICE' => PrefixTransformer::class]);
        $this->assertSame($driverExact, $registry->get('mysql', 'INV', 'ET', 'PRICE'));
        $this->assertSame($registry, app(FieldTransformerRegistry::class));
    }

    #[DataProvider('configurationLevels')]
    public function test_configuration_uses_container(string $path): void
    {
        $instance = new PrefixTransformer;
        $this->app->instance(PrefixTransformer::class, $instance);
        config(["print-kit.transformers.$path" => PrefixTransformer::class]);
        $this->assertSame($instance, app(FieldTransformerRegistry::class)->get('mysql', 'INV', 'ET', 'PRICE'));
    }

    public static function configurationLevels(): array
    {
        return [['drivers.mysql.INV.ET.PRICE'], ['drivers.mysql.INV.*.PRICE'], ['systems.INV.ET.PRICE'], ['systems.INV.*.PRICE']];
    }

    #[DataProvider('invalidConfiguration')]
    public function test_invalid_configuration(array $config, string $exception): void
    {
        config(['print-kit.transformers' => $config]);
        $this->expectException($exception);
        app(FieldTransformerRegistry::class)->get('mysql', 'INV', 'ET', 'PRICE');
    }

    public static function invalidConfiguration(): array
    {
        return [
            [['systems' => ['*' => []]], UnknownSystem::class],
            [['systems' => ['INV' => ['XX' => []]]], UnknownCategory::class],
            [['drivers' => ['*' => []]], InvalidRegistryConfiguration::class],
            [['drivers' => ['' => []]], InvalidRegistryConfiguration::class],
            [['drivers' => ['unknown' => []]], UnknownDriver::class],
            [['systems' => ['*' => ['ET' => ['PRICE' => PrefixTransformer::class]]]], UnknownSystem::class],
            [['systems' => ['INV' => ['XX' => ['PRICE' => PrefixTransformer::class]]]], UnknownCategory::class],
            [['systems' => ['INV' => ['ET' => ['PRICE' => \stdClass::class]]]], InvalidRegistryConfiguration::class],
            [['systems' => ['INV' => ['ET' => ['bad()' => PrefixTransformer::class]]]], InvalidRegistryConfiguration::class],
            [['systems' => false], InvalidRegistryConfiguration::class],
            [['INV' => []], InvalidRegistryConfiguration::class],
        ];
    }

    public function test_explicit_allowlist_replaces_defaults(): void
    {
        config(['print-kit' => ['drivers' => ['mssql' => ['label' => 'Solo SQL Server', 'description' => 'Permitido']]]]);
        (new PrintKitServiceProvider($this->app))->register();
        $this->assertSame(['mssql'], array_keys(app(DriverRegistry::class)->all()));
    }
}
