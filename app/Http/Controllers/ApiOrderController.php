<?php

/**
 * ApiOrderController - controller
 *
 * Controller to handle order-api-endpoint.
 * Check: to determine if something is to order
 * Prepare: Create necessary order-amounts
 * Execute: Execute order for prepared order-items.
 *
 */

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Demand;
use App\Models\Item;
use App\Models\Order;
use App\Models\Usage;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ApiOrderController extends Controller
{

  private function getItemsNeedingRestock(): Collection
  {
    $items = Item::withPending()->where('current_quantity', '<=', 'min_stock')->get();
    return $items->filter(fn ($item) =>
      $item->pending_quantity <= $item->min_stock &&
      $item->pending_quantity < $item->max_stock
    );
  }

  // ####################################################################################

  public function check()
  {
    $items = $this->getItemsNeedingRestock();
    return response()->json([
      "hasSome" => $items->count() > 0
    ]);
  }

  // ####################################################################################

  public function prepare()
  {

    $items = $this->getItemsNeedingRestock();

    // cancel creation, if nothing to order
    if ($items->count() === 0) {
      return response()->noContent();
    }

    $demandData = $this->prepareDemandData();
    $orderData = $this->prepareOrderData($items);
    $prepareTime = Carbon::now()->timestamp;

    return response()->json([
      "prepareTime" => $prepareTime,
      "demandData" => $demandData,
      "orderData" => $orderData,
    ]);

  }

  private function prepareDemandData(): Collection
  {
    return Demand::all()->mapWithKeys(fn ($demand) => [
      $demand->id => [
        "id" => $demand->id,
        "name" => $demand->name,
        "sp_name" => $demand->sp_name,
      ]
    ]);
  }

  private function prepareOrderData(Collection $items): Collection
  {
    return $items->map(function ($item)
    {

      $order = (object)[];

      $order->item_id = $item->id;
      $order->item_name = $item->name;
      $order->demand_id = $item->demand->id;

      $order->min = $item->min_stock;
      $order->max = $item->max_stock;

      $needForMaxStock = ($item->max_stock - $item->pending_quantity);

      $order->orderunit = $item->ordersize->unit;
      $order->orderamount = floor($needForMaxStock / $item->ordersize->amount);
      if ($order->orderamount == 0) { $order->orderamount = 1; }

      $order->baseunit = $item->basesize->unit;
      $order->amount_desired = $order->orderamount * $item->ordersize->amount;

      return $order;

    })->values();
  }

  // ####################################################################################

  public function execute(Request $request)
  {

    $minTimeRange = Carbon::now()->subWeek()->timestamp;
    $maxTimeRange = Carbon::now()->addDay()->timestamp;

    $request->validate([
      'prepareTime' => "required|integer|min:$minTimeRange|max:$maxTimeRange",
      'orderData' => 'required|array',
      'orderData.*.item_id' => 'required|integer|exists:items,id',
      'orderData.*.amount_desired' => 'required|integer|min:1'
    ]);

    $orderData = collect($request['orderData']);
    $prepareTime = $request['prepareTime'];

    DB::transaction(function () use ($orderData, $prepareTime)
    {

      // load all bookings before prepare time
      $bookings = Booking::with(['usage'])
        ->where('updated_at', '<=', Carbon::createFromTimestamp($prepareTime))
        ->whereIn('item_id', $orderData->pluck('item_id'))
        ->get();

      // create orders
      $orderData->each(function ($orderDatum) use ($bookings, $prepareTime)
      {

        // create log
        $desUsage = $orderDatum['amount_desired'];
        $desChanged = 0;
        $itemLog = $bookings->where('item_id', $orderDatum['item_id'])->map(function ($booking) use (&$desUsage, &$desChanged) {

          // increase stats values
          if ($booking->usage_id < 0) {
            $desUsage -= $booking->item_amount;
            $desChanged += $booking->item_amount;
          }

          // return log entry
          return [
            'time' => $booking->updated_at,
            'amount' => $booking->item_amount,
            'usage' => Usage::getUsageName($booking),
          ];

        })->values();

        // create order
        Order::create([
          'item_id' => $orderDatum['item_id'],
          'prepare_time' => $prepareTime,
          'amount_desired' => $orderDatum['amount_desired'],
          'amount_des_usage' => $desUsage,
          'amount_des_changed' => $desChanged,
          'amount_delivered' => 0,
          'is_order_open' => true,
          'log' => $itemLog,
        ]);

      });

      // delete bookings
      $bookings->each(function ($booking) {
        $booking->delete();
      });

    });

    return response()->noContent();

  }

}
