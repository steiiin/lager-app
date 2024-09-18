<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Item;
use App\Models\Usage;

class BookingService
{

  public function prepareData()
  {
    $bookings = Booking::all();
    $bookingData = $bookings->map(function ($booking) {

      $time = $booking->updated_at;
      $item = $booking->item;
      $demand = $item->demand->name;
      $usage = $booking->usage->name;

      return [
        'item_name' => $item->name,
        'item_demand' => $demand,
        'usage' => $usage,
        'time' => $time,
        'amount' => $booking->item_amount
      ];
    });
    return $bookingData->toArray();
  }

}
