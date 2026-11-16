<?php

namespace Glubschi\Seat\MarketOrders\Orders;

use Carbon\Carbon;
use Glubschi\Seat\MarketOrders\Pricing\Ticks;

/**
 * Compares one own order with the order book at its location.
 *
 * The competing price is the best order of other players on the same side at the same place: the lowest sell
 * order, or the highest buy order. Orders of any of the user's own characters are skipped, so two of your own
 * characters never "undercut" each other. Buy orders are compared at the same place only; a buy order elsewhere
 * whose range reaches this place also competes in the game, but is not looked at.
 *
 * Status:
 * - ahead: your price is better than every other player's.
 * - tied: someone has exactly your price (the older order sells first in EVE, so that may be you or not).
 * - undercut: someone sells cheaper (sell) or pays more (buy).
 * - alone: nobody else has an order on this side here.
 * - unknown: the order book of this place could not be read.
 *
 * The suggested price is one valid EVE price step better than the competitor (Ticks), only for tied and
 * undercut orders.
 */
final class Comparison
{
    /** EVE lets you change an order's price only 5 minutes after it was placed or last changed. */
    public const MODIFY_DELAY_SECONDS = 300;

    /** Orders that run out within this time are marked. */
    public const EXPIRES_SOON_DAYS = 3;

    /**
     * @param  array{sell: array, buy: array}|null  $book  [order_id, price] pairs, best first; null = not readable
     * @param  array<int, true>  $ownOrderIds  order IDs of all the user's characters
     * @return array{status: string, best: ?float, diff: ?float, diff_pct: ?float, suggestion: ?float, lock_seconds: int, expires_soon: bool}
     */
    public static function compare(Order $order, ?array $book, array $ownOrderIds, ?Carbon $now = null): array
    {
        $now ??= Carbon::now();
        $result = [
            'status' => 'unknown',
            'best' => null,
            'diff' => null,
            'diff_pct' => null,
            'suggestion' => null,
            'lock_seconds' => max(0, self::MODIFY_DELAY_SECONDS - (int) $order->issued->diffInSeconds($now, false)),
            'expires_soon' => $order->expires()->lt($now->copy()->addDays(self::EXPIRES_SOON_DAYS)),
        ];
        if ($book === null) {
            return $result;
        }

        $best = null;
        foreach ($book[$order->side()] ?? [] as [$id, $price]) {
            if (! isset($ownOrderIds[$id]) && $id !== $order->orderId) {
                $best = (float) $price;
                break;
            }
        }
        if ($best === null) {
            $result['status'] = 'alone';

            return $result;
        }

        // Positive: the competitor is better than you by this much.
        $diff = $order->isBuy ? $best - $order->price : $order->price - $best;
        $result['best'] = $best;
        $result['diff'] = round($diff, 2);
        $result['diff_pct'] = $order->price > 0 ? round($diff / $order->price * 100, 2) : null;

        if (abs($diff) < 0.005) {
            $result['status'] = 'tied';
        } elseif ($diff > 0) {
            $result['status'] = 'undercut';
        } else {
            $result['status'] = 'ahead';
        }
        if ($result['status'] !== 'ahead') {
            $result['suggestion'] = $order->isBuy ? Ticks::above($best) : Ticks::below($best);
        }

        return $result;
    }
}
