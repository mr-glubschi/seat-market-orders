<?php

namespace Glubschi\Seat\MarketOrders\Orders;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * The open orders of the given characters, read from SeAT's own table character_orders.
 *
 * SeAT fills it with its jobs Market\Character\Orders (open orders) and History (cancelled and expired ones).
 * An order that sold out or ran out stays "active" until the history job has run, so orders whose duration has
 * passed are left out here as well. An order that sold out early still shows until SeAT's next history update.
 *
 * Names come from SeAT's SDE (invTypes, English) and its station and structure tables.
 */
final class OwnOrders
{
    /** @return Order[] */
    public static function open(array $characterIds, ?Carbon $now = null): array
    {
        if ($characterIds === []) {
            return [];
        }
        $now ??= Carbon::now();

        $rows = DB::table('character_orders as o')
            ->leftJoin('invTypes as t', 't.typeID', '=', 'o.type_id')
            ->leftJoin('staStations as s', 's.stationID', '=', 'o.location_id')
            ->leftJoin('universe_structures as u', 'u.structure_id', '=', 'o.location_id')
            ->whereIn('o.character_id', $characterIds)
            ->where('o.state', 'active')
            ->get([
                'o.order_id', 'o.character_id', 'o.type_id', 'o.location_id', 'o.region_id', 'o.is_buy_order',
                'o.price', 'o.volume_remain', 'o.volume_total', 'o.issued', 'o.duration',
                't.typeName', 's.stationName', 'u.name as structure_name',
            ]);

        $orders = [];
        foreach ($rows as $r) {
            $order = new Order(
                orderId: (int) $r->order_id,
                characterId: (int) $r->character_id,
                typeId: (int) $r->type_id,
                typeName: $r->typeName ?? ('#' . $r->type_id),
                locationId: (int) $r->location_id,
                locationName: $r->stationName ?? $r->structure_name ?? ('#' . $r->location_id),
                isStructure: $r->stationName === null,
                regionId: (int) $r->region_id,
                isBuy: (bool) $r->is_buy_order,
                price: (float) $r->price,
                volumeRemain: (int) $r->volume_remain,
                volumeTotal: (int) $r->volume_total,
                issued: Carbon::parse($r->issued),
                durationDays: (int) $r->duration,
            );
            if ($order->expires()->gt($now)) {
                $orders[] = $order;
            }
        }

        return $orders;
    }
}
