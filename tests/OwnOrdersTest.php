<?php

namespace Glubschi\Seat\MarketOrders\Tests;

use Carbon\Carbon;
use Glubschi\Seat\MarketOrders\Markets\Books;
use Glubschi\Seat\MarketOrders\Markets\OrderBooks;
use Glubschi\Seat\MarketOrders\Orders\OwnOrders;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Reads orders from tables shaped like SeAT's (only the columns the plugin uses), in SQLite. */
class OwnOrdersTest extends TestCase
{
    private const JITA = 60003760;

    private const KEEPSTAR = 1035466617946;

    protected function setUp(): void
    {
        parent::setUp();
        Schema::create('character_orders', function (Blueprint $t) {
            $t->bigInteger('order_id');
            $t->bigInteger('character_id');
            $t->integer('type_id');
            $t->integer('region_id');
            $t->bigInteger('location_id');
            $t->boolean('is_buy_order');
            $t->double('price');
            $t->integer('volume_total');
            $t->integer('volume_remain');
            $t->dateTime('issued');
            $t->integer('duration');
            $t->string('state')->default('active');
        });
        Schema::create('invTypes', function (Blueprint $t) {
            $t->integer('typeID');
            $t->string('typeName');
        });
        Schema::create('staStations', function (Blueprint $t) {
            $t->integer('stationID');
            $t->string('stationName');
        });
        Schema::create('universe_structures', function (Blueprint $t) {
            $t->bigInteger('structure_id');
            $t->string('name');
        });

        DB::table('invTypes')->insert([['typeID' => 34, 'typeName' => 'Tritanium'], ['typeID' => 22291, 'typeName' => 'Ballistic Control System II']]);
        DB::table('staStations')->insert(['stationID' => self::JITA, 'stationName' => 'Jita IV - Moon 4 - Caldari Navy Assembly Plant']);
        DB::table('universe_structures')->insert(['structure_id' => self::KEEPSTAR, 'name' => 'RD-G2R - RD-Set-Jump']);

        $base = ['character_id' => 1, 'is_buy_order' => false, 'price' => 5.0, 'volume_total' => 10, 'volume_remain' => 4, 'duration' => 30, 'state' => 'active'];
        DB::table('character_orders')->insert([
            array_merge($base, ['order_id' => 1, 'type_id' => 34, 'region_id' => 10000002, 'location_id' => self::JITA, 'issued' => '2026-11-10 10:00:00']),
            array_merge($base, ['order_id' => 2, 'type_id' => 22291, 'region_id' => 10000023, 'location_id' => self::KEEPSTAR, 'issued' => '2026-11-15 10:00:00']),
            // Ran out of time, SeAT still says active.
            array_merge($base, ['order_id' => 3, 'type_id' => 34, 'region_id' => 10000002, 'location_id' => self::JITA, 'issued' => '2026-09-01 10:00:00']),
            // Marked expired by SeAT's history job.
            array_merge($base, ['order_id' => 4, 'type_id' => 34, 'region_id' => 10000002, 'location_id' => self::JITA, 'issued' => '2026-11-15 10:00:00', 'state' => 'expired']),
            // Another character.
            array_merge($base, ['order_id' => 5, 'character_id' => 2, 'type_id' => 34, 'region_id' => 10000002, 'location_id' => self::JITA, 'issued' => '2026-11-15 10:00:00']),
        ]);
    }

    public function test_reads_open_orders_with_names(): void
    {
        $orders = OwnOrders::open([1], Carbon::parse('2026-11-16 16:00:00'));

        $this->assertSame([1, 2], array_map(fn ($o) => $o->orderId, $orders));
        $this->assertSame('Tritanium', $orders[0]->typeName);
        $this->assertSame('Jita IV - Moon 4 - Caldari Navy Assembly Plant', $orders[0]->locationName);
        $this->assertFalse($orders[0]->isStructure);
        $this->assertSame('RD-G2R - RD-Set-Jump', $orders[1]->locationName);
        $this->assertTrue($orders[1]->isStructure);
        $this->assertSame('2026-12-10', $orders[0]->expires()->format('Y-m-d'));
    }

    public function test_station_books_come_from_one_job_per_region_and_item(): void
    {
        $orders = OwnOrders::open([1, 2], Carbon::parse('2026-11-16 16:00:00'));
        $stationOrders = array_values(array_filter($orders, fn ($o) => ! $o->isStructure));

        $this->assertFalse(Books::cached($stationOrders)['complete']);
        $jobs = Books::jobs($stationOrders, [1, 2]);
        // Two characters sell Tritanium in Jita: still one request.
        $this->assertCount(1, $jobs['public']);
        $this->assertSame([], $jobs['characters']);

        OrderBooks::putStation(self::JITA, 34, ['sell' => [[9, 4.5]], 'buy' => []], 300);
        $cached = Books::cached($stationOrders);
        $this->assertTrue($cached['complete']);
        $this->assertSame([[9, 4.5]], $cached['books'][self::JITA][34]['sell']);
    }
}
