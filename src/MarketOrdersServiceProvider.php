<?php

namespace Glubschi\Seat\MarketOrders;

use Seat\Services\AbstractSeatPlugin;

/**
 * Registers Market Orders with SeAT.
 *
 * Extends AbstractSeatPlugin (not Laravel's plain ServiceProvider) as the SeAT package development guide
 * requires: only then is the plugin listed under Settings > SeAT Settings > SeAT Module Versions and can it
 * register permissions.
 *
 * The plugin has no tables of its own: the orders come from SeAT's character_orders table.
 */
class MarketOrdersServiceProvider extends AbstractSeatPlugin
{
    /** Name used for views and translations: view('marketorders::…'), trans('marketorders::…'). */
    public const NS = 'marketorders';

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__ . '/Http/routes.php');
        $this->loadViewsFrom(__DIR__ . '/resources/views', self::NS);
        $this->loadTranslationsFrom(__DIR__ . '/resources/lang', self::NS);
    }

    public function register(): void
    {
        // Sidebar entry, merged into SeAT's menu config (package.sidebar).
        $this->mergeConfigFrom(__DIR__ . '/Config/Menu/package.sidebar.php', 'package.sidebar');

        // Permissions: the keys in this file become "marketorders.<key>" (e.g. marketorders.view).
        $this->registerPermissions(__DIR__ . '/Config/Permissions/marketorders.php', self::NS);
    }

    public function getName(): string
    {
        return 'Market Orders';
    }

    public function getPackageRepositoryUrl(): string
    {
        return 'https://github.com/mr-glubschi/seat-market-orders';
    }

    public function getPackagistPackageName(): string
    {
        return 'seat-market-orders';
    }

    public function getPackagistVendorName(): string
    {
        return 'mr-glubschi';
    }

    public function getDescription(): ?string
    {
        return "Your characters' sell and buy orders next to the best competing price, with one click to the market window in the game.";
    }
}
