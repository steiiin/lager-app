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

    // aggregate
    $rows = DB::table('bookings')
      ->selectRaw('item_id')
      ->selectRaw('date(?) as week_start', [$from->toDateString()])
      ->selectRaw("SUM(CASE WHEN usage_id >= 0 AND item_amount > 0 THEN item_amount ELSE 0 END) as consumption_total")
      ->selectRaw("MAX(CASE WHEN usage_id >= 0 AND item_amount > 0 THEN item_amount ELSE 0 END) as consumption_max")
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
        'adjustment_total',
        'booking_max',
        'booking_count',
        'aggregated_at',
      ]
    );

  }

}
