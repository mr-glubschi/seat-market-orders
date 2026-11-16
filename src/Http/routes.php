<?php

use Glubschi\Seat\MarketOrders\Http\Controllers\MarketOrdersController;
use Illuminate\Support\Facades\Route;

// Middleware as used by SeAT core and plugins: web session, logged in, the user's language,
// and the plugin permission. Route names are scoped "seatmarketorders::" (SeAT development tips).
Route::group([
    'prefix' => 'market-orders',
    'middleware' => ['web', 'auth', 'locale', 'can:marketorders.view'],
], function () {
    Route::get('/', [MarketOrdersController::class, 'index'])->name('seatmarketorders::home');
    Route::post('/orders', [MarketOrdersController::class, 'orders'])->name('seatmarketorders::orders');
    Route::get('/status', [MarketOrdersController::class, 'status'])->name('seatmarketorders::status');
    Route::post('/refresh', [MarketOrdersController::class, 'refresh'])->name('seatmarketorders::refresh');
    Route::post('/open-window', [MarketOrdersController::class, 'openWindow'])->name('seatmarketorders::open-window');
});
