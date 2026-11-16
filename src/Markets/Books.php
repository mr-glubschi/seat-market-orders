<?php

namespace Glubschi\Seat\MarketOrders\Markets;

use Glubschi\Seat\MarketOrders\Jobs\RegionTypeOrders;
use Glubschi\Seat\MarketOrders\Jobs\StructureOrders;
use Glubschi\Seat\MarketOrders\Orders\Order;

/**
 * The order books the user's orders need: what is in the cache, and the SeAT jobs that fetch what is missing.
 *
 * Adapted from Quick Sell's Books, which looks at one market; here every place with an own order is a market.
 * NPC stations: one request per item and region (/markets/{region}/orders/?type_id=), which serves every
 * station of that region with an order for the item. Upwell structures: the whole market in one job, read with
 * the token of a character who has an order there (likeliest to have access), then the user's other characters.
 */
final class Books
{
    /**
     * Cached books by location and type. A location that is not cached is missing; a structure whose market
     * nobody may read maps to null.
     *
     * @param  Order[]  $orders
     * @return array{books: array<int, array<int, array>|null>, complete: bool}
     */
    public static function cached(array $orders): array
    {
        $books = [];
        $complete = true;
        foreach (self::byLocation($orders) as $locationId => $group) {
            $typeIds = array_keys($group['types']);
            if ($group['structure']) {
                $cached = OrderBooks::structure($locationId, $typeIds);
                if ($cached === null) {
                    $denied = OrderBooks::isDenied($locationId);
                    $complete = $complete && $denied;
                    if ($denied) {
                        $books[$locationId] = null;
                    }
                    continue;
                }
                $books[$locationId] = $cached;
            } else {
                $cached = OrderBooks::station($locationId, $typeIds);
                $complete = $complete && count($cached) === count($typeIds);
                $books[$locationId] = $cached;
            }
        }

        return ['books' => $books, 'complete' => $complete];
    }

    /**
     * Jobs that fetch the books missing from the cache, split by SeAT queue.
     *
     * @param  Order[]  $orders
     * @param  int[]  $characterIds  all the user's characters
     * @return array{public: RegionTypeOrders[], characters: StructureOrders[]}
     */
    public static function jobs(array $orders, array $characterIds): array
    {
        $public = [];
        $characters = [];
        $stationsByRegionType = [];

        foreach (self::byLocation($orders) as $locationId => $group) {
            $typeIds = array_keys($group['types']);
            if ($group['structure']) {
                if (OrderBooks::structure($locationId, []) !== null || OrderBooks::isDenied($locationId)) {
                    continue;
                }
                $order = array_values(array_unique(array_merge($group['characters'], $characterIds)));
                $token = StructureOrders::firstToken($order);
                if ($token === null) {
                    // No character may read structure markets at all: nothing to wait for.
                    OrderBooks::markDenied($locationId);
                    continue;
                }
                $characters[] = new StructureOrders($token, $locationId, array_values(array_diff($order, [$token->character_id])));
            } else {
                $missing = array_diff($typeIds, array_keys(OrderBooks::station($locationId, $typeIds)));
                foreach ($missing as $typeId) {
                    $stationsByRegionType[$group['region']][$typeId][] = $locationId;
                }
            }
        }

        foreach ($stationsByRegionType as $regionId => $types) {
            foreach ($types as $typeId => $locationIds) {
                $public[] = new RegionTypeOrders($regionId, $typeId, array_values(array_unique($locationIds)));
            }
        }

        return ['public' => $public, 'characters' => $characters];
    }

    /**
     * @param  Order[]  $orders
     * @return array<int, array{structure: bool, region: int, types: array<int, true>, characters: int[]}>
     */
    private static function byLocation(array $orders): array
    {
        $groups = [];
        foreach ($orders as $o) {
            $groups[$o->locationId] ??= ['structure' => $o->isStructure, 'region' => $o->regionId, 'types' => [], 'characters' => []];
            $groups[$o->locationId]['types'][$o->typeId] = true;
            if (! in_array($o->characterId, $groups[$o->locationId]['characters'], true)) {
                $groups[$o->locationId]['characters'][] = $o->characterId;
            }
        }

        return $groups;
    }
}
