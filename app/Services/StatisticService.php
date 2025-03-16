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

  public function getLog(int $year, int $month)
  {

    $log = $this->collectLogOfMonth($year, $month);

    return [
      'by_item' => $log,
      'timespan' => [
        'month' => $month,
        'year' => $year,
      ]
    ];

  }




  public const STATS_RANEMPTY = 'ran-empty';
  public const STATS_PERITEM = 'item';

  // #####################################################################

  public function getItemsRanEmpty()
  {

    // filter orders
    $lastQuarterTime = Carbon::now()->subMonths(3)->timestamp;
    $lastMonthTime = Carbon::now()->subMonth()->timestamp;

    $lastMonth = Order::where('prepare_time', '>=', $lastMonthTime)->get();
    $lastQuarter = Order::where('prepare_time', '>=', $lastQuarterTime)->get();
    $alltime = Order::all();

    // get occurrences
    return [
      'last-month' => $this->getItemsRanEmptyFromOrders($lastMonth),
      'last-quarter' => $this->getItemsRanEmptyFromOrders($lastQuarter),
      'alltime' => $this->getItemsRanEmptyFromOrders($alltime),
    ];

  }

  private function getItemRanEmpty(int $itemId)
  {

    // filter orders
    $lastQuarterTime = Carbon::now()->subMonths(3)->timestamp;
    $lastMonthTime = Carbon::now()->subMonth()->timestamp;

    $lastMonth = Order::where('item_id', $itemId)->where('prepare_time', '>=', $lastMonthTime)->get();
    $lastQuarter = Order::where('item_id', $itemId)->where('prepare_time', '>=', $lastQuarterTime)->get();
    $alltime = Order::where('item_id', $itemId)->get();

    // get occurrences
    return [
      'last-month' => $this->getItemsRanEmptyFromOrders($lastMonth, false)[0] ?? 0,
      'last-quarter' => $this->getItemsRanEmptyFromOrders($lastQuarter, false)[0] ?? 0,
      'alltime' => $this->getItemsRanEmptyFromOrders($alltime, false)[0] ?? 0,
    ];

  }

  private function getItemsRanEmptyFromOrders($orders, $includeItemInfo = true)
  {
    $occurrences = [];
    $orders->each(function ($order) use (&$occurrences, $includeItemInfo) {
      $ordered = $order->amount_desired;
      if ($ordered >= $order->item->min_stock) {
        $itemId = $order->item->id;
        if (!isset($occurrences[$itemId]))
        {
          $occurrences[$itemId] = [
            'occurrences_count' => 0,
            'occurrences' => [],
          ];
          if ($includeItemInfo) { $occurrences[$itemId] = array_merge($occurrences[$itemId], [ 'id' => $itemId, 'name' => $order->item->name ]); }
        }
        $occurrences[$itemId]['occurrences_count'] ++;
        $occurrences[$itemId]['occurrences'][] = Carbon::createFromTimestamp($order->prepare_time);
      }
    });
    return array_values($occurrences);
  }

  // #####################################################################

  public function getItemStats(int $itemId)
  {

    $log = $this->collectLogEntries($itemId);
    return [ 'test' => $itemId ];

  }

  // #####################################################################

  private function collectLogOfMonth(int $year, int $month)
  {

    $log = collect();
    $monthStart = Carbon::create($year, $month, 1, 0, 0, 0);
    $monthEnd = $monthStart->copy()->endOfMonth();

    $orders = Order::withLogs()->whereBetween('prepare_time', [$monthStart->timestamp, $monthEnd->timestamp])->get();
    $orders->each(function ($order) use ($log) {
      if (!is_array($order->log) || empty($order->log)) { return false; }
      if (!$log->has($order->item_id)) { $log->put($order->item_id, [ 'name' => $order->item->name, 'ordered_once' => true, 'log' => collect() ]); }
      foreach ($order->log as $entry) {
        $log[$order->item_id]['log']->push($this->logEntry($entry['amount'], Carbon::parse($entry['time']), $entry['usage']));
      }
    });

    $bookings = Booking::whereBetween('updated_at', [$monthStart, $monthEnd])->get();
    $bookings->each(function ($booking) use ($log) {
      if (!$log->has($booking->item_id)) { $log->put($booking->item_id, [ 'name' => $booking->item->name, 'ordered_once' => false, 'log' => collect() ]); }
      $log[$booking->item_id]['log']->push($this->logEntry($booking->item_amount, $booking->updated_at, Usage::getUsageName($booking)));
    });

    return $log->sortKeys();

  }

  private function collectLogEntries(int $itemId)
  {

    $log = collect();
    $once_ordered = false;

    // get order logs
    $orders = Order::withLogs()->where('item_id', $itemId)->get();
    if ($orders->count() > 0) { $once_ordered = true; }
    $orders->each(function ($order) use ($log) {
      if (is_array($order->log) && !empty($order->log)) {
        foreach ($order->log as $logEntry) {
          if (!$logEntry['time']) { continue; }
          $log->push($this->logEntry(
            $logEntry['amount'],
            Carbon::parse($logEntry['time']),
            $logEntry['usage']
          ));
        }
      }
    });

    // get bookings directly as log
    $bookings = Booking::where('item_id', $itemId)->get();
    $bookings->each(function ($booking) use ($log) {
      $log->push($this->logEntry(
        $booking->item_amount,
        $booking->updated_at,
        Usage::getUsageName($booking),
      ));
    });

    return [
      'once_ordered' => $once_ordered,
      'log' => $log,
    ];

  }

  private function logEntry(int $amount, Carbon $time, string $usage)
  {
    return [
      'amount' => $amount,
      'time' => $time,
      'usage' => $usage,
    ];
  }

  private function itemEntry(Item $item)
  {
    return [
      'id' => $item->id,
      'name' => $item->name,
    ];
  }





  private Collection $orderRanEmpty;

  private function collectLogEntriess()
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
      $logEntriesByItem->push([
        'item_id' => $booking->item_id,
        'item' => $booking->item,
        'amount' => $booking->item_amount,
        'time' => $booking->updated_at,
        'usage' => Usage::getUsageName($booking),
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
    $inventory_spoiled = $entries->where('usage', 'Inv-Verfall')->sum('amount');
    $consumed_count = $entries->count();

    $timespan = $entries->min('time')->diffInWeeks($entries->max('time'));

    return [
      'average_per_use' => $consumed_count>0 ? $consumed_all/$consumed_count : 0,
      'average_per_week' => $timespan>0 ? $consumed_all/$timespan : 0,
      'consumed_all' => $consumed_all,
      'consumed_count' => $consumed_count,
      'inv-abweichung' => $inventory_corrected,
      'inv-rueckbuchung' => $inventory_undo,
      'inv-verfall' => $inventory_spoiled,
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
