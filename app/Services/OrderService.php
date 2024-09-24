<?php

namespace App\Services;

use App\Models\Item;
use App\Models\Order;
use Illuminate\Support\Carbon;

class OrderService
{

  public function getItemsNearExpiry()
  {
    
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
