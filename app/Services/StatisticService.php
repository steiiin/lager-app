<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Item;
use App\Models\Order;
use App\Models\Usage;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class StatisticService
{

  public function __construct()
  {
    $this->STARTOFSTATS = Carbon::create(2024, 9, 1, 0, 0, 0);
  }

  // #####################################################################

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

  public function getStats(int $itemId)
  {

    $trendEnd = Carbon::now()->previous(Carbon::TUESDAY);
    $trendStart = $trendEnd->copy()->subMonth();
    $quarterStart = max($this->STARTOFSTATS, $trendStart->copy()->subMonths(2));

    $logQuarter = $this->collectLogOfItemWithStats($itemId, $quarterStart, $trendStart);
    $logTrend = $this->collectLogOfItemWithStats($itemId, $trendStart, $trendEnd);

    if (empty($logQuarter)) { return [ 'nostats' => true ]; }
    else if (empty($logTrend)) { return $logQuarter; }

    $trendAvg = $logQuarter['ordered_stats']['amount_perorder']>0 ? (($logTrend['ordered_stats']['amount_perorder'] - $logQuarter['ordered_stats']['amount_perorder'])/$logQuarter['ordered_stats']['amount_perorder'])*100 : 0;
    $trendPerWeek = $logQuarter['ordered_stats']['amount_perweek']>0 ? (($logTrend['ordered_stats']['amount_perweek'] - $logQuarter['ordered_stats']['amount_perweek'])/$logQuarter['ordered_stats']['amount_perweek'])*100 : 0;

    $logQuarter['trend'] = ['trend_avg' => $trendAvg, 'trend_perweek' => $trendPerWeek];

    return $logQuarter;

  }

  // #####################################################################

  private Carbon $STARTOFSTATS;

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

  private function collectLogOfItemWithStats(int $itemId, Carbon $from, Carbon $to)
  {

    $log = [
      'item' => null,
      'ordered_once' => false,
      'too_much' => collect(),
      'ordered_stats' => [ 'amount_perweek' => 0, 'amount_perorder' => 0, 'max_amount' => 0 ],
      'amounts' => collect(),
      'ran_empty' => collect(),
    ];

    // fetch orders
    $orders = Order::withLogs()->where('item_id', $itemId)->whereBetween('prepare_time', [ $from->timestamp, $to->timestamp ])->get();
    if ($orders->count() > 0) {

      $maxOrderedAmount = $orders->max('amount_desired');
      $avgOrderedAmount = $orders->avg('amount_desired');
      $orderedStdDev = sqrt($orders->map(fn ($order) => pow($order->amount_desired - $avgOrderedAmount, 2))->avg());

      $orders->each(function ($order) use (&$log, $avgOrderedAmount, $orderedStdDev) {
        if (!is_array($order->log) || empty($order->log)) { return false; }
        if (!$log['item']) { $log['item'] = $order->item->name; $log['ordered_once'] = true; }
        if ($order->amount_desired >= $order->item->min_stock) { $log['ran_empty']->push(Carbon::createFromTimestamp($order->prepare_time)); }
        if ($order->amount_desired > ($orderedStdDev + $avgOrderedAmount)) { $log['too_much']->push([ 'time' => Carbon::createFromTimestamp($order->prepare_time), 'amount' => $order->amount_desired]); }
        foreach ($order->log as $entry) {
          if (!$log['amounts']->has($entry['usage'])) { $log['amounts']->put($entry['usage'], 0); }
          $log['amounts'][$entry['usage']] += $entry['amount'];
        }
      });

    } else {
      $maxOrderedAmount = 0;
      $avgOrderedAmount = 0;
      $orderedStdDev = 0;
      return [];
    }

    // fetch bookings
    $bookings = Booking::where('item_id', $itemId)->whereBetween('updated_at', [$from, $to])->get();
    $bookings->each(function ($booking) use (&$log) {
      if (!$log['item']) { $log['item'] = $booking->item->name; }
      $usageName = Usage::getUsageName($booking);
      if (!$log['amounts']->has($usageName)) { $log['amounts']->put($usageName, 0); }
      $log['amounts'][$usageName] += $booking->item_amount;
    });

    // create amount flow
    $flowUsage = $log['amounts']->filter(fn ($value, $key) => !Str::startsWith($key, 'Inv-'))->sum();
    $flowWhole = $log['amounts']->sum();

    $log['amounts']->put('Flow-Whole', $flowWhole);
    $log['amounts']->put('Flow-Usage', $flowUsage);

    $log['amounts'] = $log['amounts']->filter(fn ($value, $key) => (Str::startsWith($key, 'Flow-') || Str::startsWith($key, 'Inv-')));
    $log['amounts'] = $log['amounts']->sortKeys();

    // max ordered amount
    $log['ordered_stats']['max_amount'] = $maxOrderedAmount;

    // avg ordered amount
    $log['ordered_stats']['amount_perorder'] = $avgOrderedAmount;

    // per week
    $weekSpan = $from->diffInWeeks($to);

    $log['ordered_stats']['amount_perweek'] = $weekSpan>0 ? $log['amounts']['Flow-Whole'] / $weekSpan : 0;

    return $log;

  }

  private function logEntry(int $amount, Carbon $time, string $usage)
  {
    return [
      'amount' => $amount,
      'time' => $time,
      'usage' => $usage,
    ];
  }

}
