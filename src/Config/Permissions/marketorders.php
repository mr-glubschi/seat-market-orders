<?php

// Registered with scope "marketorders" in MarketOrdersServiceProvider, so "view" becomes marketorders.view.
// Labels and descriptions must be translation keys (SeAT package development guide, "Permissions").
return [
    'view' => [
        'label' => 'marketorders::permissions.view_label',
        'description' => 'marketorders::permissions.view_description',
        'division' => 'financial',
    ],
];
