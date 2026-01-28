<?php

/**
 * InventoryController - controller
 *
 * Controller for Inventory page.
 *
 */

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Demand;
use App\Models\Item;
use App\Models\Itemsize;
use App\Models\ItemsStats;
use App\Models\Order;
use App\Models\Usage;
use App\Services\StatisticService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class InventoryController extends Controller
{

  public function index()
  {
    $demands = Demand::all(['id', 'name']);
    return Inertia::render('Inventory', [
      'demands' => $demands,
    ]);
  }

  public function store(Request $request)
  {

    $request->validate([
      'id'                  => 'nullable',
      'name'                => 'required|string|max:255',
      'name_alt'            => 'nullable|string',
      'search_size'         => 'nullable|string',
      'demand_id'           => 'required|integer|exists:demands,id',
      'location'            => 'required|array',
      'location.room'       => 'nullable|string',
      'location.cab'        => 'nullable|string',
      'location.exact'      => 'nullable|string',
      'min_stock'           => 'required|numeric|min:0|max:99999',
      'max_stock'           => 'required|numeric|min:0|max:99999|gte:min_stock',
      'onvehicle_stock'     => 'required|numeric|min:0|max:99999',
      'sizes'               => 'required|array',
      'sizes.*.id'          => 'nullable|integer|exists:itemsizes,id',
      'sizes.*.unit'        => 'required|string',
      'sizes.*.amount'      => 'required|numeric|min:1|max:99999',
      'sizes.*.is_default'  => 'required|boolean',
      'checked_at'          => 'nullable|string',
      'current_expiry'      => 'nullable|string',
      'current_quantity'    => 'required|numeric|min:-99999|max:99999',
      'max_order_quantity'  => 'required|numeric|min:0|max:99999',
      'max_bookin_quantity' => 'required|numeric|min:0|max:99999',
    ]);

    DB::transaction(function () use ($request) {

      $newItem = Item::create($request->except('sizes'));
      $this->handleSizes($request->input('sizes'), $newItem);

    });

    return redirect()->route('inventory.index');
  }

  public function update(Request $request, $id)
  {

    $request->validate([
      'id'                  => 'nullable',
      'name'                => 'required|string|max:255',
      'name_alt'            => 'nullable|string',
      'search_size'         => 'nullable|string',
      'demand_id'           => 'required|integer|exists:demands,id',
      'location'            => 'required|array',
      'location.room'       => 'nullable|string',
      'location.cab'        => 'nullable|string',
      'location.exact'      => 'nullable|string',
      'min_stock'           => 'required|numeric|min:0|max:99999',
      'max_stock'           => 'required|numeric|min:0|max:99999|gte:min_stock',
      'onvehicle_stock'     => 'required|numeric|min:0|max:99999',
      'sizes'               => 'required|array',
      'sizes.*.id'          => 'nullable|integer|exists:itemsizes,id',
      'sizes.*.unit'        => 'required|string',
      'sizes.*.amount'      => 'required|numeric|min:1|max:99999',
      'sizes.*.is_default'  => 'required|boolean',
      'checked_at'          => 'nullable|string',
      'current_expiry'      => 'nullable|string',
      'current_quantity'    => 'required|numeric|min:-99999|max:99999',
      'max_order_quantity'  => 'required|numeric|min:0|max:99999',
      'max_bookin_quantity' => 'required|numeric|min:0|max:99999',
      'stockchangeReason'   => 'required|numeric|min:-3|max:-1',
    ]);

    DB::transaction(function () use ($request, $id) {

      $item = Item::findOrFail($id);

      $this->handleStockChange($request, $item);
      $item->update($request->except('sizes'));
      $this->handleSizes($request->input('sizes'), $item);
      $this->handleCheck($item);

    });

    return redirect()->route('inventory.index');
  }

  public function destroy($id)
  {

    DB::transaction(function () use ($id) {

      $item = Item::findOrFail($id);
      $bookings = Booking::where('item_id', $id);
      $sizes    = Itemsize::where('item_id', $id);
      $stats    = ItemsStats::where('item_id', $id);
      $orders   = Order::where('item_id', $id);

      $sizes->delete();
      $stats->delete();
      $bookings->delete();
      $orders->delete();
      $item->delete();

    });

    return redirect()->route('inventory.index');
  }

  // ##################################################################################

  private function handleSizes(array $sizes, Item $item)
  {
    if ($sizes) {

      // Check if any 'is_default' is true
      $hasOrderSize = collect($sizes)->contains(fn($size) => $size['is_default'] == true);

      if (!$hasOrderSize) {

        // Find the item where 'amount' === 1 and set 'is_default' to true
        foreach ($sizes as &$size) {
          if ($size['amount'] == 1) {
            $size['is_default'] = true;
            break; // Exit the loop after setting the first match
          }
        }
        unset($size); // Break the reference with the last element

      }

      foreach ($sizes as $size) {

        if ($size['id'] === null) {
          Itemsize::create(array_merge($size, ['item_id' => $item->id]));
        } else {
          $itemsize = Itemsize::findOrFail($size['id']);
          $itemsize->update($size);
        }
      }

    }
  }

  private function handleCheck(Item $item)
  {
    $item->touch('checked_at');
  }

  private function handleStockChange(Request $request, Item $item)
  {
    $change = $request->current_quantity - $item->current_quantity;
    if ($change != 0) {
      Booking::create([
        'usage_id' => $request->stockchangeReason,
        'item_id' => $item->id,
        'item_amount' => $change
      ]);
    }
  }

  // ##################################################################################

  public function cache(Request $request): JsonResponse
  {

    $usages = Usage::select(['id', 'name'])->get();
    if ($request->has('withStats')) {
      $items = Item::withPending()->withStatistic()->get()->each->appendStats();
    } else {
      $items = Item::withPending()->get();
    }

    return response()->json([
      'usages' => $usages,
      'items' => $items,
    ]);
  }

  public function jobs(): JsonResponse
  {

    $runId = (string) \Illuminate\Support\Str::uuid();
    $now = CarbonImmutable::now();
    $oldThreshold = $now->subMonths(6);

    // clean old data
    $del_orders = DB::table('orders')
      ->where('is_order_open', false)
      ->where('order_date', '<', $oldThreshold)
      ->delete();

    $del_bookings = DB::table('bookings')
      ->where('created_at', '<', $oldThreshold)
      ->delete();

    $del_stats = DB::table('itemstats')
      ->where('aggregated_at', '<', $oldThreshold)
      ->delete();

    DB::statement('VACUUM');

    // run aggregator
    $statisticService = new StatisticService();
    $statisticService->runWeeklyAggregation();

    return response()->json([
      'ok'      => true,
      'run_id'  => $runId,
      'message' => 'cleaned old data, vacuumed db and aggregated stats.',
      'counts'  => [
        'orders_deleted' => $del_orders,
        'bookings_deleted' => $del_bookings,
        'stats_deleted' => $del_stats
      ],
    ], 200);

  }

}
