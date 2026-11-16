<?php

// Sidebar entry (format documented in SeAT's package development guide, "Sidebar menu").
// One top-level link without sub-entries, like Quick Sell.
return [
    'marketorders' => [
        'name' => 'market orders',
        'label' => 'marketorders::menu.main',
        'icon' => 'fas fa-balance-scale',
        'route_segment' => 'market-orders',
        'route' => 'seatmarketorders::home',
        'permission' => 'marketorders.view',
    ],
];
