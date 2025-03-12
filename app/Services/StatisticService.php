<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Item;
use App\Models\Order;
use App\Models\Usage;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class StatisticService
{

  private Collection $orderRanEmpty;

  private function collectLogEntries()
  {

    $logEntriesByItem = collect();
    $this->orderRanEmpty = collect();

    // get order log entries
    $orders = Order::withLogs()->get();
    $orders->each(function ($order) use ($logEntriesByItem) {
      if (is_array($order->log) && !empty($order->log)) {

        // get order stats
        $minStock = $order->item->min_stock;
        $ordered = $order->amount_desired;
        if ($ordered >= $minStock)
        {
          $itemId = $order->item->id;
          $this->orderRanEmpty->push([
            'itemId' => $itemId,
            'time' => Carbon::createFromTimestamp($order->prepare_time),
          ]);
        }

        foreach ($order->log as $logEntry) {

          if (!$logEntry['time']) { continue; }
          $logEntriesByItem->push([
              'item_id' => $order->item_id,
              'item' => $order->item,
              'amount' => $logEntry['amount'] ?? null,
              'time' => Carbon::parse($logEntry['time']),
              'usage' => $logEntry['usage'] ?? null,
              'once_ordered' => true,
          ]);

        }
      }
    });

    // get bookings (not ordered yet)
    $bookings = Booking::all();
    $bookings->each(function ($booking) use ($logEntriesByItem) {

      // get usage name
      $usageName = $booking->usage_id < 0
      ? Usage::getInternalUsageName($booking->usage_id)
      : $booking->usage->name;

      $logEntriesByItem->push([
        'item_id' => $booking->item_id,
        'item' => $booking->item,
        'amount' => $booking->item_amount,
        'time' => $booking->updated_at,
        'usage' => $usageName,
      ]);

    });

    $logEntriesByItem = $logEntriesByItem->groupBy('item_id')->sortKeys();
    return $logEntriesByItem->values();

  }

  public function generateStatistic()
  {

    $logEntries = $this->collectLogEntries();
    return $logEntries->map(fn ($entries) => $this->generateItemStatistic($entries));

  }

  private function generateItemStatistic($entries)
  {

    $item = $entries[0]['item'];
    $once_ordered = $entries->some('once_ordered', true);

    $stats = [];

    $allTime = $this->generateItemStatsProperties($entries);
    if ($allTime) {
      $allTimeEmpty = $this->orderRanEmpty->where('itemId', $item->id)->count();
      $stats['alltime'] = array_merge($allTime, [ 'ranEmpty' => $allTimeEmpty ]);
    }

    $quarterTime = Carbon::now()->subMonths(3);
    $lastQuarter = $this->generateItemStatsProperties($entries->where('time', '>=', $quarterTime));
    if ($lastQuarter) {
      $lastQuarterEmpty = $this->orderRanEmpty->where('itemId', $item->id)->where('time', '>=', $quarterTime)->count();
      $stats['last-quarter'] = array_merge($lastQuarter, [ 'ranEmpty' => $lastQuarterEmpty ]);
    }

    $weekTime = Carbon::now()->subWeek();
    $lastWeek = $this->generateItemStatsProperties($entries->where('time', '>=', $weekTime));
    if ($lastWeek) {
      $lastWeekEmpty = $this->orderRanEmpty->where('itemId', $item->id)->where('time', '>=', $weekTime)->count();
      $stats['last-week'] = array_merge($lastWeek, [ 'ranEmpty' => $lastWeekEmpty ]);
    }

    return [
      'item' => [
        'name' => $item->name,
        'specs' => [
          'min_stock' => $item->min_stock,
          'max_stock' => $item->max_stock,
          'current_quantity' => $item->current_quantity,
          'once_ordered' => $once_ordered,
        ],
        'stats' => $stats,
      ]
    ];

  }

  private function generateItemStatsProperties($entries)
  {

    if ($entries->count() == 0) { return null; }

    $consumed_all = $entries->sum('amount');
    $inventory_corrected = $entries->where('usage', 'Inv-Abweichung')->sum('amount');
    $inventory_undo = $entries->where('usage', 'Inv-Rückbuchung')->sum('amount');
    $consumed_count = $entries->count();

    $timespan = $entries->min('time')->diffInWeeks($entries->max('time'));

    return [
      'average_per_use' => $consumed_count>0 ? $consumed_all/$consumed_count : 0,
      'average_per_week' => $timespan>0 ? $consumed_all/$timespan : 0,
      'consumed_all' => $consumed_all,
      'consumed_count' => $consumed_count,
      'inv-abweichung' => $inventory_corrected,
      'inv-rueckbuchung' => $inventory_undo,
    ];

  }

  private function fetchAllItems()
  {
    $items = Item::withStats()->get();
    return $items;
  }

  private function createStatsForRange(Collection $orders, Item $item)
  {

    $startDate = Carbon::createFromTimestamp($orders->min('prepare_time'));
    $endDate = Carbon::createFromTimestamp($orders->max('prepare_time'));
    $diffInWeeks = $startDate->diffInWeeks($endDate);

    $avgDemand = $orders->sum('amount_desired') / $diffInWeeks;
    $maxDemand = $orders->max('amount_desired');
    $minDemand = $orders->min('amount_desired');
    $stockOutOccurences = $orders->where('amount_desired', '>=', $item->max_stock - $item->min_stock)->count();

    $sd = $this->calcStandardDeviation($orders->pluck('amount_desired'));

    return [
      'avgDemand' => $avgDemand,
      'maxDemand' => $maxDemand,
      'minDemand' => $minDemand,
      'stockOutOccurences' => $stockOutOccurences,
      'sd' => $sd,
    ];

  }

  private function createStats(Collection $items)
  {

    $recentThreshold = Carbon::now()->subDays(28);

    return $items->map(function ($item) use ($recentThreshold) {

      $allOrders = $item->allOrders;
      $recentOrders = $item->allOrders->where('prepare_time', '>=', $recentThreshold->timestamp);

      if ($recentOrders->count() == 0)
      {
        return null;
      }

      // stats
      $statsAll = $this->createStatsForRange($allOrders, $item);
      $statsRecent = $this->createStatsForRange($recentOrders, $item);

      // trend
      $trend = $statsAll['avgDemand'] == 0
        ? 0
        : ($statsRecent['avgDemand'] - $statsAll['avgDemand']) / $statsAll['avgDemand'] * 100;

      // forecast
      $recentWeight = 0.7;
      $forecastDemand = ceil(($statsRecent['avgDemand'] * $recentWeight) + ($statsAll['avgDemand'] * (1 - $recentWeight)));
      $forecastSd = ($statsRecent['sd'] * $recentWeight) + ($statsAll['sd'] * (1 - $recentWeight));

      // min/max-stock
      $leadTime = 1.285; // Montag bis Mittwoch = 9d = 9d/7d = 1.285 Wochen
      $targetTime = 3;

      $safetyStock = 1.65 * $forecastSd * sqrt($leadTime);
      $forecastMin = ($forecastDemand * $leadTime) + $safetyStock;
      $forecastMax = $forecastMin + ($forecastDemand * $targetTime);

      return [
        'item' => $item->name,
        'all' => $statsAll,
        'recent' => $statsRecent,
        'trend' => round($trend, 2),
        'forecast' => [
          'demand' => $forecastDemand,
          'safetyStock' => $safetyStock,
          'min' => $forecastMin,
          'max' => $forecastMax,
        ],
      ];

    })->filter(function ($item) {
      return !is_null($item);
    });
  }

  public function generateAll()
  {

    $items = $this->fetchAllItems();
    return $this->createStats($items);

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

  // ##########################################################################

  private function calcStandardDeviation($amounts)
  {
    $count = $amounts->count();
    if ($count > 1) {
      $mean = $amounts->avg();
      $sumOfSquares = $amounts->reduce(function ($carry, $value) use ($mean) {
          return $carry + pow($value - $mean, 2);
      }, 0);
      $variance = $sumOfSquares / ($count - 1);
      return sqrt($variance);
    } else {
      return 0;
    }
  }

}
