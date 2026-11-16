<?php

namespace Glubschi\Seat\MarketOrders\Tests;

use Glubschi\Seat\MarketOrders\MarketOrdersServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;
use Seat\Services\Contracts\EsiClient;

/**
 * Boots a bare Laravel app with only the Market Orders provider. SeAT's own providers are not loaded:
 * they need MariaDB, Redis and the SDE. Tables the plugin reads are created per test in SQLite.
 */
abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [MarketOrdersServiceProvider::class];
    }

    /** SQLite in memory instead of MariaDB, and the array cache instead of Redis. */
    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
        $app['config']->set('cache.default', 'array');

        // SeAT's ESI client is bound by SeAT's own providers, which these tests do not boot. Jobs only need
        // it to exist; no test talks to ESI.
        $app->instance(EsiClient::class, \Mockery::mock(EsiClient::class));
    }
}
