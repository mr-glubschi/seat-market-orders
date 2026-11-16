<?php

return [
    'title' => 'Market Orders',
    'description' => 'Die Market Orders deiner Charaktere',

    'characters' => 'Deine Charaktere',
    'characters_help' => 'Offene Orders, wie SeAT sie gespeichert hat. Die Order-Tabellen mit den Preisen der Konkurrenz kommen in der nächsten Version.',
    'col_character' => 'Charakter',
    'col_sell' => 'Sell Orders',
    'col_buy' => 'Buy Orders',
    'col_scope' => 'Fenster im Spiel öffnen',
    'col_online' => 'Online',
    'scope_ok' => 'erlaubt',
    'scope_missing' => 'Scope fehlt',
    'scope_missing_help' => 'Mit diesem Charakter in SeAT neu einloggen und esi-ui.open_window.v1 erlauben.',
    'online_yes' => 'online',
    'online_no' => 'offline',
    'online_unknown' => 'unbekannt',

    'test' => 'Test: Marktfenster im Spiel öffnen',
    'test_help' => 'Öffnet im EVE-Client des gewählten Charakters das Fenster Market Details für das Item. Laut ESI muss der Charakter eingeloggt sein; bitte einmal online und einmal offline testen.',
    'character' => 'Charakter',
    'item' => 'Item (englischer Name oder Type-ID)',
    'open' => 'Im Spiel öffnen',

    'opened' => 'Market Details von :item an den Client von :character geschickt.',
    'error_character' => 'Dieser Charakter ist nicht mit deinem SeAT-Konto verknüpft.',
    'error_scope' => 'Dieser Charakter hat den Scope esi-ui.open_window.v1 nicht erlaubt.',
    'error_item' => 'Kein handelbares Item mit diesem englischen Namen oder dieser Type-ID in SeAT.',
    'error_esi' => 'ESI antwortete :status: :error',
    'error' => 'Etwas ist schiefgegangen. Bitte nochmals versuchen.',
];
