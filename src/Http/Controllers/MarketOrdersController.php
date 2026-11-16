<?php

namespace Glubschi\Seat\MarketOrders\Http\Controllers;

use Glubschi\Seat\MarketOrders\Jobs\OpenMarketDetails;
use Glubschi\Seat\MarketOrders\Markets\Books;
use Glubschi\Seat\MarketOrders\Orders\Comparison;
use Glubschi\Seat\MarketOrders\Orders\Order;
use Glubschi\Seat\MarketOrders\Orders\OwnOrders;
use Glubschi\Seat\MarketOrders\Pricing\Ticks;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Seat\Eseye\Exceptions\RequestFailedException;
use Seat\Eveapi\Jobs\Market\Character\History;
use Seat\Eveapi\Jobs\Market\Character\Orders as SeatOrdersJob;
use Seat\Eveapi\Models\RefreshToken;
use Seat\Web\Http\Controllers\Controller;

/**
 * The Market Orders page and its JSON endpoints.
 *
 * Flow (as in Quick Sell): the page asks orders() for the chosen characters. When order books are missing from
 * the cache, SeAT jobs are dispatched as batches and their IDs returned; the page polls status() and asks again
 * with no_fetch once the batches are done. Everything works on the logged-in user's own characters only.
 */
class MarketOrdersController extends Controller
{
    /** User setting: the characters chosen last time, comma-separated IDs. */
    public const CHARACTERS_SETTING = 'glubschi.marketorders.characters';

    /** User setting: split, sell or buy. */
    public const VIEW_SETTING = 'glubschi.marketorders.view';

    public const VIEWS = ['split', 'sell', 'buy'];

    /** SeAT's scope for reading a character's market orders (its Orders and History jobs need it). */
    private const ORDERS_SCOPE = 'esi-markets.read_character_orders.v1';

    private const SESSION_BATCHES = 'glubschi.marketorders.batches';

    public function index()
    {
        $characters = $this->overview();
        $saved = array_map('intval', array_filter(explode(',', (string) setting(self::CHARACTERS_SETTING))));
        $selected = array_values(array_intersect($saved, array_column($characters, 'id')));
        if ($selected === []) {
            $selected = array_column($characters, 'id');
        }
        $view = setting(self::VIEW_SETTING);

        return view('marketorders::index', [
            'characters' => $characters,
            'selected' => $selected,
            'view' => in_array($view, self::VIEWS, true) ? $view : 'split',
            // SeAT's image helper: portraits from CCP's image server, as on SeAT's own pages.
            'portraits' => array_combine(array_column($characters, 'id'), array_map(
                fn ($id) => img('characters', 'portrait', $id, 32, [], false), array_column($characters, 'id'))),
        ]);
    }

    /** The open orders of the chosen characters, each compared with the best price of other players. */
    public function orders(Request $request): JsonResponse
    {
        $request->validate([
            'characters' => 'nullable|array',
            'characters.*' => 'integer',
            'view' => 'nullable|in:' . implode(',', self::VIEWS),
            'no_fetch' => 'nullable|boolean',
        ]);

        $all = $this->characters();
        $chosen = array_values(array_intersect(array_map('intval', (array) $request->input('characters', [])), array_keys($all)));
        setting([self::CHARACTERS_SETTING, implode(',', $chosen)]);
        if ($request->filled('view')) {
            setting([self::VIEW_SETTING, $request->input('view')]);
        }

        $orders = OwnOrders::open($chosen);

        $cached = Books::cached($orders);
        if (! $cached['complete'] && ! $request->boolean('no_fetch')) {
            $batches = $this->dispatch(Books::jobs($orders, array_keys($all)));
            if ($batches !== []) {
                return response()->json(['status' => 'pending', 'batches' => $batches]);
            }
            $cached = Books::cached($orders);
        }

        // Orders of every one of the user's characters are skipped as competitors, not only the chosen ones.
        $ownIds = DB::table('character_orders')->whereIn('character_id', array_keys($all))->where('state', 'active')
            ->pluck('order_id')->mapWithKeys(fn ($id) => [(int) $id => true])->all();

        $rows = [];
        foreach ($orders as $order) {
            $book = array_key_exists($order->locationId, $cached['books'])
                ? ($cached['books'][$order->locationId] === null ? null : ($cached['books'][$order->locationId][$order->typeId] ?? null))
                : null;
            $rows[] = $this->row($order, Comparison::compare($order, $book, $ownIds), $all);
        }

        return response()->json([
            'status' => 'done',
            'rows' => $rows,
            'updated' => now()->format('H:i'),
        ]);
    }

    /** Progress of the batches started by this page; only batches of this session can be read. */
    public function status(Request $request): JsonResponse
    {
        $own = session(self::SESSION_BATCHES, []);
        $ids = array_values(array_intersect((array) $request->query('batches', []), $own));
        $total = 0;
        $done = 0;
        $finished = true;
        foreach ($ids as $id) {
            $batch = Bus::findBatch($id);
            if ($batch === null) {
                continue;
            }
            $total += $batch->totalJobs;
            $done += $batch->processedJobs() + $batch->failedJobs;
            $finished = $finished && ($batch->finished() || $batch->cancelled() || $batch->pendingJobs <= $batch->failedJobs);
        }

        return response()->json(['finished' => $finished, 'total' => $total, 'done' => min($done, $total)]);
    }

    /**
     * Asks SeAT to read the chosen characters' orders from ESI now, with SeAT's own jobs (Orders and History),
     * instead of waiting for SeAT's next regular update. ESI still answers from its cache (up to 20 minutes for
     * open orders), so a change made in the game a moment ago may not be there yet.
     */
    public function refresh(Request $request): JsonResponse
    {
        $request->validate(['characters' => 'nullable|array', 'characters.*' => 'integer']);
        $chosen = array_values(array_intersect(array_map('intval', (array) $request->input('characters', [])), array_keys($this->characters())));

        $jobs = [];
        foreach (RefreshToken::whereIn('character_id', $chosen)->get() as $token) {
            // SeAT's scope middleware would drop a job without the scope silently.
            if ($token->hasScope(self::ORDERS_SCOPE)) {
                $jobs[] = new SeatOrdersJob($token);
                $jobs[] = new History($token);
            }
        }

        return response()->json(['batches' => $this->dispatch(['public' => [], 'characters' => $jobs], 'Market Orders refresh')]);
    }

    /** Opens the Market Details window of an item in the game client of one of the user's characters. */
    public function openWindow(Request $request): JsonResponse
    {
        $request->validate([
            'character_id' => 'required|integer',
            'type_id' => 'required|integer',
        ]);

        $characters = $this->characters();
        $characterId = (int) $request->input('character_id');
        if (! array_key_exists($characterId, $characters)) {
            return $this->fail(trans('marketorders::global.error_character'), 403);
        }

        $token = RefreshToken::find($characterId);
        if ($token === null || ! $token->hasScope(OpenMarketDetails::SCOPE)) {
            return $this->fail(trans('marketorders::global.error_scope', ['character' => $characters[$characterId]]), 422);
        }

        $type = DB::table('invTypes')->where('typeID', (int) $request->input('type_id'))->first(['typeID', 'typeName']);
        if ($type === null) {
            return $this->fail(trans('marketorders::global.error_item'), 422);
        }

        try {
            (new OpenMarketDetails($token, (int) $type->typeID))->handle();
        } catch (RequestFailedException $e) {
            $response = $e->getEsiResponse();

            return $this->fail(trans('marketorders::global.error_esi', ['status' => $response->getErrorCode(), 'error' => $response->error()]), 502);
        } catch (\Throwable $e) {
            report($e);

            return $this->fail(trans('marketorders::global.error_esi', ['status' => $e->getCode() ?: '?', 'error' => $e->getMessage()]), 502);
        }

        // For a character who is not logged in, nothing happens in the game and ESI reports no error either
        // (tested 2026-11-16). So the page says when SeAT last saw the character offline.
        $online = DB::table('character_onlines')->where('character_id', $characterId)->value('online');
        $offline = $online !== null && ! (bool) $online;

        return response()->json([
            'ok' => true,
            'message' => trans($offline ? 'marketorders::global.opened_offline' : 'marketorders::global.opened',
                ['item' => $type->typeName, 'character' => $characters[$characterId]]),
        ]);
    }

    private function row(Order $o, array $c, array $characters): array
    {
        $fmt = fn (?float $v) => $v === null ? null : number($v, 2);

        return [
            'order_id' => $o->orderId,
            'side' => $o->side(),
            'character_id' => $o->characterId,
            'character' => $characters[$o->characterId] ?? (string) $o->characterId,
            'type_id' => $o->typeId,
            'type' => $o->typeName,
            // SeAT's image helper: the item's icon from CCP's image server, as on SeAT's own pages.
            'icon' => img('types', 'icon', $o->typeId, 32, ['class' => 'img-circle eve-icon small-icon'], false),
            'location_id' => $o->locationId,
            'location' => $o->locationName,
            'price' => $o->price,
            'remain' => $o->volumeRemain,
            'total' => $o->volumeTotal,
            'value' => $o->price * $o->volumeRemain,
            'expires' => $o->expires()->toIso8601String(),
            'status' => $c['status'],
            'best' => $c['best'],
            'diff' => $c['diff'],
            'diff_pct' => $c['diff_pct'],
            'suggestion' => $c['suggestion'],
            // Plain number for the price field of EVE's Modify Order window: no thousands separator.
            'suggestion_copy' => $c['suggestion'] === null ? null : Ticks::format($c['suggestion']),
            'lock_seconds' => $c['lock_seconds'],
            'expires_soon' => $c['expires_soon'],
            'fmt' => [
                'price' => $fmt($o->price),
                'best' => $fmt($c['best']),
                'diff' => $c['diff'] === null ? null : number(abs($c['diff']), 2),
                'diff_pct' => $c['diff_pct'] === null ? null : number(abs($c['diff_pct']), 2),
                'suggestion' => $fmt($c['suggestion']),
                'remain' => number($o->volumeRemain, 0),
                'total' => number($o->volumeTotal, 0),
                'expires' => $o->expires()->format('Y-m-d'),
            ],
        ];
    }

    /**
     * The user's characters with open order counts, scope and online status.
     *
     * @return array<int, array{id: int, name: string, sell: int, buy: int, scope: bool, online: ?bool}>
     */
    private function overview(): array
    {
        $characters = $this->characters();
        $ids = array_keys($characters);

        $counts = [];
        foreach (OwnOrders::open($ids) as $o) {
            $counts[$o->characterId][$o->side()] = ($counts[$o->characterId][$o->side()] ?? 0) + 1;
        }

        $online = DB::table('character_onlines')->whereIn('character_id', $ids)->pluck('online', 'character_id');
        $tokens = RefreshToken::whereIn('character_id', $ids)->get()->keyBy('character_id');

        $out = [];
        foreach ($characters as $id => $name) {
            $out[] = [
                'id' => $id,
                'name' => $name,
                'sell' => $counts[$id]['sell'] ?? 0,
                'buy' => $counts[$id]['buy'] ?? 0,
                'scope' => isset($tokens[$id]) && $tokens[$id]->hasScope(OpenMarketDetails::SCOPE),
                'online' => isset($online[$id]) ? (bool) $online[$id] : null,
            ];
        }

        return $out;
    }

    /** @return array<int, string> names of the user's characters by ID */
    private function characters(): array
    {
        return auth()->user()->characters()->orderBy('character_infos.name')->pluck('character_infos.name', 'character_infos.character_id')
            ->mapWithKeys(fn ($name, $id) => [(int) $id => $name])->all();
    }

    /**
     * Dispatches jobs as batches, one per SeAT queue, and remembers the batch IDs in the session for status().
     *
     * @return string[] batch IDs
     */
    private function dispatch(array $jobs, string $name = 'Market Orders'): array
    {
        $ids = [];
        foreach (['public' => $jobs['public'], 'characters' => $jobs['characters']] as $queue => $list) {
            if ($list === []) {
                continue;
            }
            // SeAT's own batches set the queue on the batch (a batch pushes all its jobs to one queue).
            $ids[] = Bus::batch($list)->name($name)->onQueue($queue)->allowFailures()->dispatch()->id;
        }
        if ($ids !== []) {
            session([self::SESSION_BATCHES => array_slice(array_merge(session(self::SESSION_BATCHES, []), $ids), -20)]);
        }

        return $ids;
    }

    private function fail(string $message, int $status): JsonResponse
    {
        return response()->json(['ok' => false, 'message' => $message], $status);
    }
}
