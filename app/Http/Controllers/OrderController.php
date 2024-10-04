<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Demand;
use App\Models\Item;
use App\Models\Order;
use App\Models\Usage;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{

  private function getItemsBelowMinStock()
  {
    $items = Item::withAll()->where('current_quantity', '<=', 'min_stock')->get();
    return $items->filter(function ($item) {
      return ($item->demanded_quantity <= $item->min_stock) && ($item->demanded_quantity < $item->max_stock);
    });
  }

  public function check()
  {
    $items = $this->getItemsBelowMinStock();
    return response()->json([
      "hasSome" => $items->count() > 0
    ]);
  }

  public function prepare()
  {
    
    // check if there are items to order
    $items = $this->getItemsBelowMinStock();
    if ($items->count() === 0) {
      return response()->noContent();
    }

    // create demand data
    $demandData = Demand::all()->mapWithKeys(function ($demand) 
    {
      return [
        $demand->id => [
          "id" => $demand->id,
          "name" => $demand->name,
          "sp_name" => $demand->sp_name,
        ]
      ];
    })->toArray();

    // save timestamp
    $prepareTime = Carbon::now()->timestamp;

    // create orderdata
    $orderData = $items->map(function ($item)
    {

      $order = (object)[];

      $order->item_id = $item->id;
      $order->item_name = $item->name;
      $order->demand_id = $item->demand->id;

      $order->min = $item->min_stock;
      $order->max = $item->max_stock;

      $needForMaxStock = ($item->max_stock - $item->demanded_quantity);

      $order->orderunit = $item->ordersize->unit;
      $order->orderamount = floor($needForMaxStock / $item->ordersize->amount);
      if ($order->orderamount == 0) { $order->orderamount = 1; }

      $order->baseunit = $item->basesize->unit;
      $order->amount_desired = $order->orderamount * $item->ordersize->amount;

      return $order;
      
    })->values();

    return response()->json([
      "prepareTime" => $prepareTime,
      "demandData" => $demandData,
      "orderData" => $orderData,
    ]);

  }

  public function execute(Request $request)
  {

    $request->validate([
      'prepareTime' => 'required|integer|min:1727100000|max:3000000000',
      'orderData' => 'required|array',
      'orderData.0' => 'required',
      'orderData.*.item_id' => 'required|integer|exists:items,id',
      'orderData.*.amount_desired' => 'required|integer|min:1'
    ]);
    $orderData = collect($request['orderData']);
    $prepareTime = $request['prepareTime'];

    DB::transaction(function () use ($orderData, $prepareTime) 
    {

      // load all bookings before preparetime
      $prepareDate = Carbon::createFromTimestamp($prepareTime);
      $bookings = Booking::with(['usage'])->where('updated_at', '<=', $prepareDate)->get();
      
      // create orders
      $orderData->each(function ($orderDatum) use ($bookings, $prepareTime) 
      {

        // create log
        $desUsage = $orderDatum['amount_desired'];
        $desChanged = 0;
        $itemLog = $bookings->where('item_id', $orderDatum['item_id'])->map(function ($booking) use (&$desUsage, &$desChanged) {

          // get usage name
          $usageName = $booking->usage_id < 0 
            ? Usage::getInternalUsageName($booking->usage_id)
            : $booking->usage->name;

          // increase stats values
          if ($booking->usage_id < 0) {
            $desUsage -= $booking->item_amount;
            $desChanged += $booking->item_amount;
          }

          // return log entry
          return [
            'time' => $booking->updated_at,
            'amount' => $booking->item_amount,
            'usage' => $usageName,
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

      return response()->noContent();

    });

  }

}
