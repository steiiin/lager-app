<?php

namespace App\Services;

use App\Models\Item;
use App\Models\Order;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

class StatisticService
{

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

      $allOrders = $item->closedOrders;
      $recentOrders = $item->closedOrders->where('prepare_time', '>=', $recentThreshold->timestamp);

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
