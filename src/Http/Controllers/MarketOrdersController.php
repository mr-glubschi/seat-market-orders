<?php

namespace Glubschi\Seat\MarketOrders\Http\Controllers;

use Carbon\Carbon;
use Glubschi\Seat\MarketOrders\Jobs\OpenMarketDetails;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Seat\Eseye\Exceptions\RequestFailedException;
use Seat\Eveapi\Models\RefreshToken;
use Seat\Web\Http\Controllers\Controller;

/**
 * The Market Orders page.
 *
 * v0.1 is the skeleton: it lists the user's characters with what the plugin will need (open orders in SeAT,
 * the open window scope, online status) and has a test to open an item's market window in the game. The
 * order tables follow in v0.2. Everything works on the logged-in user's own characters only.
 */
class MarketOrdersController extends Controller
{
    public function index()
    {
        return view('marketorders::index', [
            'characters' => $this->overview(),
        ]);
    }

    /** Opens the Market Details window of an item in the game client of one of the user's characters. */
    public function openWindow(Request $request): JsonResponse
    {
        $request->validate([
            'character_id' => 'required|integer',
            'item' => 'required|string|max:100',
        ]);

        $characterId = (int) $request->input('character_id');
        if (! array_key_exists($characterId, $this->characters())) {
            return $this->fail(trans('marketorders::global.error_character'), 403);
        }

        $token = RefreshToken::find($characterId);
        if ($token === null || ! $token->hasScope(OpenMarketDetails::SCOPE)) {
            return $this->fail(trans('marketorders::global.error_scope'), 422);
        }

        $type = $this->type(trim($request->input('item')));
        if ($type === null) {
            return $this->fail(trans('marketorders::global.error_item'), 422);
        }

        try {
            (new OpenMarketDetails($token, (int) $type->typeID))->handle();
        } catch (RequestFailedException $e) {
            $response = $e->getEsiResponse();

            return response()->json([
                'ok' => false,
                'status' => $response->getErrorCode(),
                'message' => trans('marketorders::global.error_esi', ['status' => $response->getErrorCode(), 'error' => $response->error()]),
            ], 502);
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'ok' => false,
                'status' => $e->getCode() ?: null,
                'message' => trans('marketorders::global.error_esi', ['status' => $e->getCode() ?: '?', 'error' => $e->getMessage()]),
            ], 502);
        }

        return response()->json([
            'ok' => true,
            'message' => trans('marketorders::global.opened', ['item' => $type->typeName, 'character' => $this->characters()[$characterId]]),
        ]);
    }

    /**
     * The user's characters with open order counts, scope and online status.
     *
     * Open orders: SeAT marks an order as cancelled or expired only when its order history job runs, so an
     * order whose duration has passed is not counted even if SeAT still says "active".
     *
     * @return array<int, array{id: int, name: string, sell: int, buy: int, scope: bool, online: ?bool}>
     */
    private function overview(): array
    {
        $characters = $this->characters();
        $ids = array_keys($characters);

        $counts = [];
        $now = Carbon::now();
        $orders = DB::table('character_orders')->whereIn('character_id', $ids)->where('state', 'active')
            ->get(['character_id', 'is_buy_order', 'issued', 'duration']);
        foreach ($orders as $o) {
            if ($o->issued && Carbon::parse($o->issued)->addDays((int) $o->duration)->lt($now)) {
                continue;
            }
            $side = $o->is_buy_order ? 'buy' : 'sell';
            $counts[$o->character_id][$side] = ($counts[$o->character_id][$side] ?? 0) + 1;
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

    /** An item by English name or type ID, from SeAT's SDE table invTypes; only items that can be traded. */
    private function type(string $item): ?object
    {
        $query = DB::table('invTypes')->whereNotNull('marketGroupID');
        $query = ctype_digit($item) ? $query->where('typeID', (int) $item) : $query->where('typeName', $item);

        return $query->first(['typeID', 'typeName']);
    }

    /** @return array<int, string> names of the user's characters by ID */
    private function characters(): array
    {
        return auth()->user()->characters()->orderBy('character_infos.name')->pluck('character_infos.name', 'character_infos.character_id')
            ->mapWithKeys(fn ($name, $id) => [(int) $id => $name])->all();
    }

    private function fail(string $message, int $status): JsonResponse
    {
        return response()->json(['ok' => false, 'status' => null, 'message' => $message], $status);
    }
}
