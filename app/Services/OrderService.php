<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Demand;
use App\Models\Item;
use App\Models\Order;
use App\Models\Usage;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class OrderService
{

  

  public function getItemsNearExpiry()
  {
    $thresholdDate = Carbon::now()->addDays(21); 
    return Item::with(['demand'])->whereDate('current_expiry', '<=', $thresholdDate)->get();
  }

  public function hasSome()
  {
    
  }

  public function prepare()
  {


  }

  public function execute()
  {


    // create orderdata
    $orderData = collect();

    // create orders
    DB::transaction(function () use ($orderData) 
    {

      // load all bookings
      $bookings = Booking::with(['usage'])->get();
      
      // create orders
      $orderData->each(function ($orderDatum) use ($bookings) 
      {

        // create log
        $log = $bookings->where('item_id', $orderDatum->item->id)->map(function ($booking) 
        {
          return [
            'timestamp' => $booking->updated_at,
            'amount' => $booking->item_amount,
            'usage' => $booking->usage->name
          ];
        })->values();
        
        // create order
        Order::create([
          'item_id' => $orderDatum->item->id,
          'amount_desired' => $orderDatum->amount_desired,
          'amount_delivered' => 0,
          'is_order_open' => true,
          'log' => $log
        ]);

      });

      // clear bookings
      Booking::truncate();

    });

    return $responseData->toArray();

  }

  public function fetchLogs()
  {

    $orders = Order::closed()->get();
    $groupedData = $orders->groupBy(function ($order)
    {
      return $order->item->name;
    })->map(function ($orders, $itemName) 
    {

      $allLogs = collect();
      $amountSum = 0;

      foreach ($orders as $order)
      {
        
      }

    });

  }

}
