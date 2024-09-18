<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Item;
use App\Models\Usage;

class OrderService
{

  public function getItemsBelowMinStock()
  {
    return Item::with(['demand', 'basesize', 'ordersize'])->where('current_quantity', '<=', 'min_stock')->get();
  }

  public function prepareData()
  {
    $items = $this->getItemsBelowMinStock();
    $orderData = $items->map(function ($item) {

      $ordersize = $item->ordersize;
      $basesize = $item->basesize;

      $amountNeeded = ($item->max_stock - ($item->current_quantity));
      $amountBySize = floor($amountNeeded / $ordersize->amount);
      $amountDesired = $amountBySize * $ordersize->amount;

      return [
        'item_name' => $item->name,
        'current_quantity' => $item->current_quantity,
        'min_stock' => $item->min_stock,
        'max_stock' => $item->max_stock,
        'baseunit' => $basesize->unit,
        'amount_desired' => $amountDesired,
        'bySize_amount' => $amountBySize,
        'bySize_unit' => $ordersize->unit,
      ];
    });
    return $orderData->toArray();
  }

}
