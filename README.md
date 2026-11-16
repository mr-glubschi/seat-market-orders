# Market Orders for SeAT

A [SeAT 5](https://github.com/eveseat/seat) plugin that shows the open sell and buy orders of your characters
next to the best competing price at the same market, and opens an item's market window in the game with one
click, so you can adjust the order right away.

> **Status: v0.2 (order tables).**

Sister plugin: [Quick Sell](https://github.com/mr-glubschi/seat-quick-sell).

## What it does

- **Characters:** pick one, several or all of your characters (remembered). Each shows its open sell / buy
  orders, whether it is online, and a warning when it cannot open windows in the game.
- **View:** split (sell orders on top, buy orders below), sell only or buy only; filter by location, item
  name, or only undercut orders.
- **Per order:** item icon and name, location, character, quantity left / total, your price, the best price
  of other players at the same place, the difference in ISK and %, status, suggested price and expiry.
- **Status:** *undercut* (someone sells cheaper or pays more), *same price*, *ahead*, *alone* (nobody else
  on this side there), or *?* when the market could not be read. Orders of all your characters are ignored
  as competitors. Buy orders are compared at the same place only; buy orders elsewhere whose range reaches
  your place are not included.
- **Suggestion:** one valid EVE price step better than the competitor (at most four significant digits, as
  the client allows). One click copies the price (plain number, as the *Modify Order* window takes it) and
  opens the item's *Market Details* window in the game for the character who owns the order.
- **Lock:** EVE allows a price change only 5 minutes after the order was placed or changed; the page shows
  how long is left, counted from the order's `issued` time.
- **Refresh:** asks SeAT to read the chosen characters' orders from ESI now, with SeAT's own jobs.

## Data and storage

| Data | Source | Kept |
| --- | --- | --- |
| Your orders | SeAT's table `character_orders` (SeAT's jobs `Market\Character\Orders` and `History`) | read only |
| Online status, characters | SeAT's tables `character_onlines`, `character_infos` | read only |
| Item, station and structure names | SeAT's SDE (`invTypes`, `staStations`) and `universe_structures` | read only |
| Order books (10 best orders per side and item) | ESI, fetched by SeAT jobs: `/markets/{region}/orders/?type_id=` for NPC stations, `/markets/structures/{id}/` with a character token | Laravel cache, exactly as long as ESI's `Expires` header says (5 minutes) |
| Chosen characters and view | you | SeAT user settings |
| Open window in game | ESI `POST /ui/openwindow/marketdetails/`, only on your click | nothing |

The plugin has no tables of its own. The market code (order books, jobs, price steps) is copied from Quick
Sell on purpose, so each plugin can be installed on its own.

Structure markets need a character with the `esi-markets.structure_markets.v1` scope (part of SeAT's
default scopes) and access to the structure's market; characters with an order there are tried first.

Opening a window needs the scope `esi-ui.open_window.v1` (part of SeAT's default login scopes) and,
according to CCP's ESI documentation, a character who is logged in to the game. For a character who is not
logged in nothing happens and ESI reports no error, so the page says when SeAT last saw it offline. A
character without the scope is marked on the page; log in with it again in SeAT to grant it.

ESI caches a character's open orders for up to 20 minutes, so a change you just made in the game shows up
in SeAT with that delay.

## Install

Market Orders is installed like any other SeAT plugin (see SeAT's
[community packages guide](https://github.com/eveseat/docs/blob/master/docs/community_packages.md)):

1. Add the package to `SEAT_PLUGINS` in your SeAT `.env`, comma-separated with other plugins, for example
   `SEAT_PLUGINS=mr-glubschi/seat-quick-sell,mr-glubschi/seat-market-orders`.
2. Restart the SeAT stack. On start, SeAT installs the plugin.
3. In SeAT, give the permission **View Market Orders** (`marketorders.view`) to a role, under
   *Settings → Access Management*. Administrators have it automatically.

## Roadmap

| Version | Content |
| --- | --- |
| 0.1 | Skeleton: package, menu entry, permission, character overview, test to open the market window in game |
| 0.2 | Order tables with competing prices, status, suggested price, copy and open in one click, refresh |
| 0.3 | Relist fee, market depth, order slots, recent sales, link to Quick Sell |

## Development

```bash
composer install
vendor/bin/phpunit
```

The tests boot the plugin with [Orchestra Testbench](https://github.com/orchestral/testbench) without
the rest of SeAT. To try the plugin in a running SeAT, follow SeAT's
[developer installation](https://github.com/eveseat/docs/blob/master/docs/developer_guides/developer_installation.md):
clone this repository into the `packages` folder of your SeAT stack and register it in
`packages/override.json`:

```json
{
  "autoload": { "Glubschi\\Seat\\MarketOrders\\": "packages/mr-glubschi/seat-market-orders/src/" },
  "providers": [ "Glubschi\\Seat\\MarketOrders\\MarketOrdersServiceProvider" ]
}
```

## License

GPL-2.0-or-later, like SeAT.
