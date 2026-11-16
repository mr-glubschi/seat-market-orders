<?php

namespace Glubschi\Seat\MarketOrders\Tests;

use Glubschi\Seat\MarketOrders\MarketOrdersServiceProvider;
use Illuminate\Support\Facades\Route;

class ServiceProviderTest extends TestCase
{
    private const MIDDLEWARE = ['web', 'auth', 'locale', 'can:marketorders.view'];

    public function test_registers_the_routes_with_seat_middleware(): void
    {
        $routes = [
            'home' => ['market-orders', 'GET'],
            'orders' => ['market-orders/orders', 'POST'],
            'status' => ['market-orders/status', 'GET'],
            'refresh' => ['market-orders/refresh', 'POST'],
            'open-window' => ['market-orders/open-window', 'POST'],
        ];
        foreach ($routes as $name => [$uri, $method]) {
            $route = Route::getRoutes()->getByName('seatmarketorders::' . $name);
            $this->assertNotNull($route, $name);
            $this->assertSame($uri, $route->uri());
            $this->assertContains($method, $route->methods());
            $this->assertSame(self::MIDDLEWARE, $route->gatherMiddleware());
        }
    }

    public function test_registers_the_view_permission(): void
    {
        $permission = config('seat.permissions.marketorders.view');

        $this->assertIsArray($permission);
        $this->assertSame('marketorders::permissions.view_label', $permission['label']);
        $this->assertSame('financial', $permission['division']);
    }

    public function test_adds_a_sidebar_entry_guarded_by_the_permission(): void
    {
        $entry = config('package.sidebar.marketorders');

        $this->assertSame('seatmarketorders::home', $entry['route']);
        $this->assertSame('marketorders.view', $entry['permission']);
        $this->assertSame('market-orders', $entry['route_segment']);
    }

    public function test_reports_name_and_packagist_coordinates(): void
    {
        $provider = new MarketOrdersServiceProvider($this->app);

        $this->assertSame('Market Orders', $provider->getName());
        $this->assertSame('mr-glubschi', $provider->getPackagistVendorName());
        $this->assertSame('seat-market-orders', $provider->getPackagistPackageName());
    }

    public function test_translation_keys_resolve(): void
    {
        $this->assertSame('Market Orders', trans('marketorders::menu.main'));
        $this->assertSame('View Market Orders', trans('marketorders::permissions.view_label'));
        $this->assertSame('Game', trans('marketorders::global.open'));
    }
}
