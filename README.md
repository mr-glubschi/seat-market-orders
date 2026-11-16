# Market Orders for SeAT

A [SeAT 5](https://github.com/eveseat/seat) plugin that shows the open sell and buy orders of your characters
next to the best competing price at the same market, and opens an item's market window in the game with one
click, so you can adjust the order right away.

> **Status: v0.1 (skeleton).** The page lists your characters with their open orders, whether they allow
> opening windows in the game, and whether they are online, plus a test to open an item's market window.
> The order tables follow in v0.2.

Sister plugin: [Quick Sell](https://github.com/mr-glubschi/seat-quick-sell).

## Planned (v0.2)

- Pick one, several or all of your characters; split view (sell on top, buy below) or one side only.
- Per order: item icon and name, location, character, quantity left / total, your price, the best price of
  other players at the same place (your own characters' orders ignored), difference, status
  (ahead, undercut, alone), expiry, and the 5-minute lock after a price change.
- Suggested price one valid EVE price step ahead (at most four significant digits). One click copies it and
  opens the item's *Market Details* window in the client of the character who owns the order.

## Data and storage

| Data | Source | Kept |
| --- | --- | --- |
| Your orders | SeAT's table `character_orders` (SeAT's jobs `Market\Character\Orders` and `History`) | read only |
| Online status, characters | SeAT's tables `character_onlines`, `character_infos` | read only |
| Item names | SeAT's SDE (`invTypes`, English) | read only |
| Open window in game | ESI `POST /ui/openwindow/marketdetails/`, only on your click | nothing |

The plugin has no tables of its own.

Opening a window needs the scope `esi-ui.open_window.v1` (part of SeAT's default login scopes) and,
according to CCP's ESI documentation, a character who is logged in to the game. A character without the
scope is marked on the page; log in with it again in SeAT to grant it.

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
| 0.2 | Order tables with competing prices, status, suggested price, copy and open in one click |
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
