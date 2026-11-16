<?php

return [
    'title' => 'Market Orders',
    'description' => "Your characters' market orders",

    'characters' => 'Your characters',
    'characters_help' => 'Open orders as SeAT has stored them. The order tables with competing prices follow in the next version.',
    'col_character' => 'Character',
    'col_sell' => 'Sell orders',
    'col_buy' => 'Buy orders',
    'col_scope' => 'Open window in game',
    'col_online' => 'Online',
    'scope_ok' => 'allowed',
    'scope_missing' => 'scope missing',
    'scope_missing_help' => 'Log in with this character again in SeAT and grant esi-ui.open_window.v1.',
    'online_yes' => 'online',
    'online_no' => 'offline',
    'online_unknown' => 'unknown',

    'test' => 'Test: open the market window in the game',
    'test_help' => 'Opens the Market Details window of the item in the EVE client of the chosen character. ESI says the character must be logged in; try it once online and once offline.',
    'character' => 'Character',
    'item' => 'Item (English name or type ID)',
    'open' => 'Open in game',

    'opened' => 'Market Details of :item sent to the client of :character.',
    'error_character' => 'This character is not linked to your SeAT account.',
    'error_scope' => 'This character has not granted the scope esi-ui.open_window.v1.',
    'error_item' => 'No tradeable item with this English name or type ID in SeAT.',
    'error_esi' => 'ESI answered :status: :error',
    'error' => 'Something went wrong. Please try again.',
];
