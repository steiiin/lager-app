<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Item;
use App\Models\Order;
use App\Models\Usage;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class OrderService
{

  public function getItemsBelowMinStock()
  {
    $items = Item::withAll()->where('current_quantity', '<=', 'min_stock')->get();
    return $items->filter(function ($item)
    {
      return ($item->demanded_quantity <= $item->min_stock) && ($item->demanded_quantity < $item->max_stock);
    });
  }

  public function getItemsNearExpiry()
  {
    /** 
     * @disregard 
     */
    $thresholdDate = Carbon::now()->addDays(21); 
    return Item::with(['demand'])->whereDate('current_expiry', '<=', $thresholdDate)->get();
  }

  public function hasSome()
  {
    $items = $this->getItemsBelowMinStock();
    return $items->count() > 0;
  }

  public function execute()
  {

    // check if there are items to order
    $items = $this->getItemsBelowMinStock();
    if ($items->count() === 0) {
      return [];
    }

    // create orderdata
    $orderData = $items->map(function ($item)
    {

      $order = (object)[];

      $ordersize = $item->ordersize;
      $basesize = $item->basesize;

      $needForMaxStock = ($item->max_stock - $item->demanded_quantity);

      $order->item = $item;
      $order->baseunit = $basesize->unit;
      $order->ordersize = $ordersize;

      $order->timesOrdersize = floor($needForMaxStock / $ordersize->amount);
      $order->amount_desired = $order->timesOrdersize * $ordersize->amount;

      return $order;
      
    });

    // create response data
    $responseData = $orderData->map(function ($order)
    {
      return [
        'item_name' => $order->item->name,
        'current_quantity' => $order->item->current_quantity,
        'min_stock' => $order->item->min_stock,
        'max_stock' => $order->item->max_stock,
        'baseunit' => $order->baseunit,
        'amount' => $order->amount_desired,
        'bysize_times' => $order->timesOrdersize,
        'bysize_unit' => $order->ordersize->unit,
      ];
    })->values();

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
