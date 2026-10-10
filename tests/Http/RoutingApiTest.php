<?php

declare(strict_types=1);

namespace Ahinest\LaravelPrintKit\Tests\Http;

use Ahinest\LaravelPrintKit\PrintKitServiceProvider;
use Ahinest\LaravelPrintKit\Tests\Fixtures\ApiGate;
use Ahinest\LaravelPrintKit\Tests\TestCase;

class RoutingApiTest extends TestCase
{
    public function test_api_disabled_by_default_does_not_register_routes(): void
    {
        $this->assertFalse(config('print-kit.api.enabled'));
        $this->assertFalse(app('router')->has('print-kit.formats.index'));
        $this->getJson('/print-kit/formats')->assertNotFound();
    }

    public function test_custom_prefix_middleware_names_and_runtime_disabling(): void
    {
        config(['print-kit.api.enabled' => true, 'print-kit.api.prefix' => 'designer', 'print-kit.api.middleware' => [ApiGate::class]]);
        (new PrintKitServiceProvider($this->app))->boot();
        app('router')->getRoutes()->refreshNameLookups();
        $this->assertTrue(app('router')->has('print-kit.formats.index'));
        $names = ['formats.index', 'formats.store', 'formats.show', 'formats.update', 'formats.destroy',
            'options.drivers', 'options.systems', 'options.categories', 'options.paper-sizes', 'options.fields',
            'design.validate', 'preview', 'formats.preview',
            'paper-sizes.index', 'paper-sizes.store', 'paper-sizes.show', 'paper-sizes.update', 'paper-sizes.destroy',
            'fields.index', 'fields.store', 'fields.show', 'fields.update'];
        foreach ($names as $name) {
            $route = app('router')->getRoutes()->getByName('print-kit.'.$name);
            $this->assertNotNull($route);
            $route->prepareForSerialization();
            $this->assertNotEmpty(serialize($route));
        }
        $this->assertSame('designer/options/fields', app('router')->getRoutes()->getByName('print-kit.options.fields')->uri());
        $this->getJson('/designer/options/drivers')->assertForbidden();
        $this->getJson('/designer/options/drivers', ['X-Allow' => 'yes'])->assertOk();
        $this->getJson('/print-kit/options/drivers')->assertNotFound();
        config(['print-kit.api.enabled' => false]);
        $this->getJson('/designer/options/drivers', ['X-Allow' => 'yes'])->assertNotFound();
    }

    public function test_empty_middleware_list_is_not_merged_with_api_default(): void
    {
        config(['print-kit.api' => ['enabled' => true, 'middleware' => []]]);
        $provider = new PrintKitServiceProvider($this->app);
        $provider->register();
        $provider->boot();
        $this->assertSame([], config('print-kit.api.middleware'));
        $route = app('router')->getRoutes()->getByName('print-kit.preview');
        if ($route === null) {
            app('router')->getRoutes()->refreshNameLookups();
            $route = app('router')->getRoutes()->getByName('print-kit.preview');
        }
        $this->assertNotContains('api', $route->middleware());
    }
}
