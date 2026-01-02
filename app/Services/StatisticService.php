<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class StatisticService
{

  public function runWeeklyAggregation()
  {

    // set date range
    $to = CarbonImmutable::now()
      ->startOfWeek(CarbonImmutable::MONDAY)
      ->startOfDay();
    $from = $to->subWeek();

    // Consumption values: only usage_id >= 0 AND item_amount > 0
    $consVal = "CASE WHEN usage_id >= 0 AND item_amount > 0 THEN item_amount END"; // NULL when not applicable

    // Population stddev: sqrt(avg(x^2) - avg(x)^2), computed only on matching rows (AVG ignores NULL)
    $avgX  = "COALESCE(AVG($consVal), 0)";
    $avgX2 = "COALESCE(AVG(($consVal) * ($consVal)), 0)";
    $variance  = "MAX(($avgX2) - (($avgX) * ($avgX)), 0)";
    $stddevExpr = "sqrt($variance)";

    // aggregate
    $rows = DB::table('bookings')
      ->selectRaw('item_id')
      ->selectRaw('date(?) as week_start', [$from->toDateString()])
      ->selectRaw("SUM(CASE WHEN usage_id >= 0 AND item_amount > 0 THEN item_amount ELSE 0 END) as consumption_total")
      ->selectRaw("MAX(CASE WHEN usage_id >= 0 AND item_amount > 0 THEN item_amount ELSE 0 END) as consumption_max")
      ->selectRaw("$stddevExpr as consumption_stddev")
      ->selectRaw("SUM(CASE WHEN usage_id < 0 THEN item_amount ELSE 0 END) as adjustment_total")
      ->selectRaw("MAX(ABS(item_amount)) as booking_max")
      ->selectRaw("COUNT(*) as booking_count")
      ->where('created_at', '>=', $from)
      ->where('created_at', '<', $to)
      ->groupBy('item_id')
      ->get();

    $now = now();

    $payload = $rows->map(fn ($r) => [
      'item_id'            => (int) $r->item_id,
      'week_start'         => $r->week_start, // 'YYYY-MM-DD'
      'consumption_total'  => (int) $r->consumption_total,
      'consumption_max'    => (int) $r->consumption_max,
      'consumption_stddev' => (float) $r->consumption_stddev,
      'adjustment_total'   => (int) $r->adjustment_total,
      'booking_max'        => (int) $r->booking_max,
      'booking_count'      => (int) $r->booking_count,
      'aggregated_at'      => $now,
    ])->all();

    DB::table('items_stats')->upsert(
      $payload,
      ['item_id', 'week_start'],
      [
        'consumption_total',
        'consumption_max',
        'consumption_stddev',
        'adjustment_total',
        'booking_max',
        'booking_count',
        'aggregated_at',
      ]
    );

  }

}
