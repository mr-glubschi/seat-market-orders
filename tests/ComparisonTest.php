<?php

namespace Glubschi\Seat\MarketOrders\Tests;

use Carbon\Carbon;
use Glubschi\Seat\MarketOrders\Orders\Comparison;
use Glubschi\Seat\MarketOrders\Orders\Order;
use PHPUnit\Framework\TestCase;

class ComparisonTest extends TestCase
{
    private Carbon $now;

    protected function setUp(): void
    {
        $this->now = Carbon::parse('2026-11-16 16:00:00');
    }

    private function order(bool $buy, float $price, string $issued = '2026-11-16 12:00:00', int $days = 90, int $id = 1): Order
    {
        return new Order($id, 314354929, 22291, 'Ballistic Control System II', 1035466617946, 'RD-G2R - RD-Set-Jump', true,
            10000023, $buy, $price, 7, 16, Carbon::parse($issued), $days);
    }

    public function test_sell_order_undercut_gets_one_step_below(): void
    {
        // Real case from 2026-11-16: 815,900 against 800,000 at RD-Set-Jump.
        $c = Comparison::compare($this->order(false, 815900), ['sell' => [[9, 800000.0]], 'buy' => []], [1 => true], $this->now);

        $this->assertSame('undercut', $c['status']);
        $this->assertSame(800000.0, $c['best']);
        $this->assertSame(15900.0, $c['diff']);
        $this->assertSame(1.95, $c['diff_pct']);
        $this->assertSame(799900.0, $c['suggestion']);
    }

    public function test_own_orders_of_all_characters_are_skipped(): void
    {
        // Order 5 belongs to another of the user's characters and is cheaper: not a competitor.
        $book = ['sell' => [[5, 790000.0], [1, 800000.0], [9, 810000.0]], 'buy' => []];
        $c = Comparison::compare($this->order(false, 800000), $book, [1 => true, 5 => true], $this->now);

        $this->assertSame('ahead', $c['status']);
        $this->assertSame(810000.0, $c['best']);
        $this->assertNull($c['suggestion']);
    }

    public function test_same_price_is_tied_and_gets_a_suggestion(): void
    {
        $c = Comparison::compare($this->order(false, 36930), ['sell' => [[9, 36930.0]], 'buy' => []], [], $this->now);

        $this->assertSame('tied', $c['status']);
        $this->assertSame(36920.0, $c['suggestion']);
    }

    public function test_buy_order_outbid_gets_one_step_above(): void
    {
        $c = Comparison::compare($this->order(true, 21500), ['sell' => [], 'buy' => [[9, 22900.0], [1, 21500.0]]], [1 => true], $this->now);

        $this->assertSame('undercut', $c['status']);
        $this->assertSame(1400.0, $c['diff']);
        $this->assertSame(22910.0, $c['suggestion']);
    }

    public function test_alone_and_unknown(): void
    {
        $alone = Comparison::compare($this->order(false, 100), ['sell' => [[1, 100.0]], 'buy' => [[9, 50.0]]], [1 => true], $this->now);
        $unknown = Comparison::compare($this->order(false, 100), null, [], $this->now);

        $this->assertSame('alone', $alone['status']);
        $this->assertSame('unknown', $unknown['status']);
        $this->assertNull($unknown['suggestion']);
    }

    public function test_modify_lock_and_expiry(): void
    {
        $fresh = Comparison::compare($this->order(false, 100, '2026-11-16 15:58:00'), null, [], $this->now);
        $old = Comparison::compare($this->order(false, 100, '2026-11-16 15:50:00', 1), null, [], $this->now);

        $this->assertSame(180, $fresh['lock_seconds']);
        $this->assertFalse($fresh['expires_soon']);
        $this->assertSame(0, $old['lock_seconds']);
        $this->assertTrue($old['expires_soon']);
    }
}
