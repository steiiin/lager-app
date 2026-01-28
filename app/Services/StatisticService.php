<?php

namespace App\Services;

use App\Models\Booking;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
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
      ->selectRaw("SUM(CASE WHEN usage_id < 0 AND usage_id != -4 THEN item_amount ELSE 0 END) as adjustment_total")
      ->selectRaw("MAX(ABS(item_amount)) as booking_max")
      ->selectRaw("COUNT(*) as booking_count")
      ->where('created_at', '>=', $from)
      ->where('created_at', '<', $to)
      ->groupBy('item_id')
      ->get();

    $now = now();

    $payload = $rows->map(fn($r) => [
      'item_id'            => (int) $r->item_id,
      'week_start'         => $r->week_start, // 'YYYY-MM-DD'
      'consumption_total'  => (int) $r->consumption_total,
      'consumption_max'    => (int) $r->consumption_max,
      'adjustment_total'   => (int) $r->adjustment_total,
      'booking_max'        => (int) $r->booking_max,
      'booking_count'      => (int) $r->booking_count,
      'aggregated_at'      => $now,
    ])->all();

    DB::table('itemstats')->upsert(
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

  // ##########################################################################

  public function findLowScanShiftSignals()
  {

    $to = CarbonImmutable::now()->subDays(4);
    $from = $to->subYear()->max(Booking::first()->created_at);

    $shiftData = $this->collectShiftData($from, $to);

    $signals = ["all" => [], "hyg" => []];
    $shiftData->each(function ($dates, $shift) use (&$signals) {

      $dates = $this->addRollingAvg($dates);
      $dates->each(function ($dateData, $dateString) use ($shift, &$signals) {

        $date = CarbonImmutable::parse($dateString);
        if ($dateData['amount_all'] < $dateData['avg_all']) {
          $signals['all'][] = array_merge($dateData->toArray(), [
            "date" => $date->toDateString(),
            "shift" => $shift,
          ]);
        } else if ($dateData['amount_hyg'] < $dateData['avg_hyg']) {
          $signals['hyg'][] = array_merge($dateData->toArray(), [
            "date" => $date->toDateString(),
            "shift" => $shift,
          ]);
        }
      });
    });

    return $signals;
  }

  // ##########################################################################

  private function collectShiftData(CarbonImmutable $from, CarbonImmutable $to): Collection
  {

    $bookings = Booking::whereIn('usage_id', [1, 2]) // usage_id RTW1 / RTW2
      ->where('created_at', '>=', $from)
      ->where('created_at', '<', $to)
      ->orderBy('created_at')
      ->get()
      ->map(fn($row) => [
        'id' => (int) $row->id,
        'datetime' => CarbonImmutable::parse($row->created_at),
        'usage_id' => (int) $row->usage_id,
        'amount' => (int) $row->item_amount,
        'item' => (string) $row->item->name,
        'demand' => (string) $row->item->demand->name,
      ])
      ->all();

    $shiftData = collect([]);
    foreach ($bookings as $booking) {

      $shift = $this->matchShift($booking);
      if (!$shift) {
        continue;
      }

      $shiftName = $shift['name'];
      if (!$shiftData->has($shiftName)) {
        $shiftData->put($shiftName, collect([]));
      }

      $dateKey = $shift['date']->toISOString();
      if (!$shiftData[$shiftName]->has($dateKey)) {
        $shiftData[$shiftName]->put($dateKey, collect([
          "bookings" => collect([]),
          "amount_all" => 0,
          "amount_hyg" => 0,
        ]));
      }

      $shiftData[$shiftName][$dateKey]['bookings']->push($booking);
      $shiftData[$shiftName][$dateKey]['amount_all'] += $booking['amount'];
      $shiftData[$shiftName][$dateKey]['amount_hyg'] += ($booking['demand'] == 'Hygiene' ? $booking['amount'] : 0);
    }

    return $shiftData;
  }

  private function matchShift(array $booking): ?array
  {
    $usage_id = $booking['usage_id'];
    $datetime = $booking['datetime'];

    if ($usage_id == 1) {

      // Co1
      if ($datetime->between(
        $datetime->startOfDay()->addMinutes(330),   // 05:30
        $datetime->startOfDay()->addMinutes(1050),  // 17:30
      )) {

        return [
          "name" => "Co1",
          "date" => $datetime->startOfDay()->addMinutes(360),
        ];
      }

      // CoN
      else {

        $date = ($datetime->secondsUntilEndOfDay() <= 23400)
          ? $datetime->startOfDay()->addMinutes(1050)
          : $datetime->startOfDay()->subMinutes(390);

        return [
          "name" => "CoN",
          "date" => $date,
        ];
      }
    }
    if ($usage_id == 2) {

      // Co2
      if ($datetime->between(
        $datetime->startOfDay()->addMinutes(390),   // 06:30
        $datetime->startOfDay()->addMinutes(1170),  // 19:30
      )) {

        return [
          "name" => "Co2",
          "date" => $datetime->startOfDay()->addMinutes(420)
        ];
      }
    }
    return null;
  }

  private function addRollingAvg(Collection $dates, int $n = 7, ?string $tz = null): Collection
  {
    $tz   = $tz ?? config('app.timezone', 'UTC'); // e.g. 'Europe/Berlin'
    $half = intdiv($n, 2);

    if ($dates->isEmpty()) {
      return $dates;
    }

    // 1) Normalize input to a map keyed by local day: Y-m-d
    $byDay = $dates->mapWithKeys(function ($item, $key) use ($tz) {
      $dt  = CarbonImmutable::parse($key)->setTimezone($tz);
      $day = $dt->toDateString(); // local day boundary

      $arr = $item instanceof Collection ? $item->toArray() : (array) $item;

      return [$day => collect(array_merge([
        'date'       => $day,
        'shift_start' => $dt->startOfDay()->toIso8601String(), // optional
        'amount_all' => 0.0,
        'amount_hyg' => 0.0,
      ], [
        // keep your existing values if present
        'amount_all' => (float) ($arr['amount_all'] ?? 0),
        'amount_hyg' => (float) ($arr['amount_hyg'] ?? 0),
      ], $arr))];
    });

    // 2) Build a contiguous daily series from min..max day
    $minDay = CarbonImmutable::parse($byDay->keys()->min(), $tz)->startOfDay();
    $maxDay = CarbonImmutable::parse($byDay->keys()->max(), $tz)->startOfDay();

    $series = collect();
    for ($d = $minDay; $d->lte($maxDay); $d = $d->addDay()) {
      $k = $d->toDateString();

      $series[$k] = $byDay->get($k, collect([
        'date'       => $k,
        'shift_start' => $d->toIso8601String(), // optional
        'amount_all' => 0.0,
        'amount_hyg' => 0.0,
      ]));
    }

    // 3) Rolling avg over day windows; divide by day count (not entry count)
    return $series->map(function (Collection $item, string $dayKey) use ($series, $half, $tz) {
      $center = CarbonImmutable::parse($dayKey, $tz)->startOfDay();

      $start = $center->subDays($half);
      $end   = $center->addDays($half);

      $sumAll = 0.0;
      $sumHyg = 0.0;
      $dayCount = 0;

      for ($d = $start; $d->lte($end); $d = $d->addDay()) {
        $k = $d->toDateString();

        // Only count days that exist in the series (edges => smaller window)
        if (!$series->has($k)) {
          continue;
        }

        $row = $series[$k];
        $sumAll += (float) ($row['amount_all'] ?? 0);
        $sumHyg += (float) ($row['amount_hyg'] ?? 0);
        $dayCount++;
      }

      return $item->merge([
        'avg_all' => $dayCount > 0 ? $sumAll / $dayCount : 0.0,
        'avg_hyg' => $dayCount > 0 ? $sumHyg / $dayCount : 0.0,
      ]);
    });
  }

  // ##########################################################################

  /**
   * Detect low scan shifts for defined shift types and ambulances.
   *
   * Assumptions:
   * - bookings table has: created_at (timestamp), usage_id (int)
   * - usage_id == 1 => ambulance 1 bookings (co1 + con)
   * - usage_id == 2 => ambulance 2 bookings (co2)
   * - usage_id < 0 are manual corrections (excluded here)
   *
   * @return array<int, array<string,mixed>>
   */
  public function findLowScanShiftSignalsx(
    CarbonImmutable $from,
    CarbonImmutable $to,
    int $baselineWindow = 12,          // number of previous same-type shifts for baseline
    float $ratioThreshold = 0.55,      // scans < baseline * ratio => anomaly (practical)
    float $minBaseline = 4.0,          // ignore baseline smaller than this (avoids noisy low-volume shifts)
    int $dayPadding = 7                // history padding; ensures enough baseline shifts
  ): array {
    // 1) Build shift instances (with padding for baseline history)
    $startDay = $from->startOfDay()->subDays($dayPadding);
    $endDay   = $to->startOfDay()->addDays($dayPadding);

    $shifts = $this->buildShifts($startDay, $endDay);

    // 2) Compute min/max to query bookings once
    usort($shifts, fn($a, $b) => $a['start'] <=> $b['start']);
    $minShiftStart = $shifts[0]['start'];
    $maxShiftEnd   = end($shifts)['end'];

    // 3) Load bookings for relevant usage_id streams (exclude manual/corrections)
    $bookings = DB::table('bookings')
      ->select(['created_at', 'usage_id'])
      ->whereIn('usage_id', [1, 2])
      ->where('created_at', '>=', $minShiftStart)
      ->where('created_at', '<', $maxShiftEnd)
      ->orderBy('created_at')
      ->get()
      ->map(fn($row) => [
        't' => CarbonImmutable::parse($row->created_at),
        'u' => (int) $row->usage_id,
      ])
      ->all();

    // 4) Count scans per shift efficiently (single pass per usage stream)
    $this->countScansPerShift($shifts, $bookings);

    // 5) Compute rolling baselines per shift type (name) and flag anomalies
    $anomalies = $this->detectLowScanAnomalies(
      $shifts,
      $baselineWindow,
      $ratioThreshold,
      $minBaseline
    );

    // 6) Return only anomalies overlapping requested window
    return array_values(array_filter($anomalies, function ($a) use ($from, $to) {
      $start = $a['start'];
      $end   = $a['end'];
      return $start < $to && $end > $from; // overlap
    }));
  }

  /**
   * Build shift instances. Adjust times to match your actual station schedule.
   *
   * Returns array of:
   * - name: string (co1, con, co2)
   * - usage_id: int (1 or 2)
   * - start/end: CarbonImmutable
   * - scans: int
   */
  private function buildShifts(CarbonImmutable $startDay, CarbonImmutable $endDay): array
  {
    $shifts = [];

    for ($day = $startDay; $day <= $endDay; $day = $day->addDay()) {
      // Ambulance 1 day shift (co1) 06:00-18:00
      $shifts[] = [
        'name'     => 'co1',
        'usage_id' => 1,
        'start'    => $day->setTime(5, 30),
        'end'      => $day->setTime(17, 30),
        'scans'    => 0,
      ];

      // Ambulance 1 night shift (con) 18:00-06:00 next day
      $shifts[] = [
        'name'     => 'con',
        'usage_id' => 1,
        'start'    => $day->setTime(17, 30),
        'end'      => $day->addDay()->setTime(5, 30),
        'scans'    => 0,
      ];

      // Ambulance 2 day shift (co2) 07:00-19:00
      $shifts[] = [
        'name'     => 'co2',
        'usage_id' => 2,
        'start'    => $day->setTime(6, 30),
        'end'      => $day->setTime(19, 30),
        'scans'    => 0,
      ];
    }

    // Sort by start time for later processing
    usort($shifts, fn($a, $b) => $a['start'] <=> $b['start']);

    return $shifts;
  }

  /**
   * Efficient scan counting:
   * - bookings are sorted by time
   * - shifts are sorted by start
   * - each booking is consumed at most once for its usage_id stream
   */
  private function countScansPerShift(array &$shifts, array $bookings): void
  {
    // Split bookings by usage_id to avoid cross-stream work
    $b1 = [];
    $b2 = [];

    foreach ($bookings as $b) {
      if ($b['u'] === 1) $b1[] = $b['t'];
      if ($b['u'] === 2) $b2[] = $b['t'];
    }

    // Count for usage stream 1 and 2 independently
    $this->countForUsage($shifts, 1, $b1);
    $this->countForUsage($shifts, 2, $b2);
  }

  private function countForUsage(array &$shifts, int $usageId, array $times): void
  {
    $i = 0;
    $n = count($times);

    foreach ($shifts as &$shift) {
      if ($shift['usage_id'] !== $usageId) {
        continue;
      }

      $start = $shift['start'];
      $end   = $shift['end'];

      // Advance pointer to first booking >= start
      while ($i < $n && $times[$i] < $start) {
        $i++;
      }

      // Count bookings in [start, end)
      $count = 0;
      $j = $i;
      while ($j < $n && $times[$j] < $end) {
        $count++;
        $j++;
      }

      $shift['scans'] = $count;

      // Move pointer to end of this shift window
      $i = $j;
    }
  }

  /**
   * Detect low scan anomalies per shift type using rolling median baseline.
   *
   * Returns anomaly objects including baseline + deviation + severity.
   */
  private function detectLowScanAnomalies(
    array $shifts,
    int $baselineWindow,
    float $ratioThreshold,
    float $minBaseline
  ): array {
    // Group shifts by shift type name
    $byName = [
      'co1' => [],
      'con' => [],
      'co2' => [],
    ];

    foreach ($shifts as $idx => $shift) {
      $byName[$shift['name']][] = $idx;
    }

    $anomalies = [];

    foreach ($byName as $name => $indices) {
      // Rolling baseline from previous shifts of same name
      for ($k = 0; $k < count($indices); $k++) {
        $idx = $indices[$k];

        $current = $shifts[$idx];
        $currentScans = (int) $current['scans'];

        // Need enough history
        $histStart = max(0, $k - $baselineWindow);
        $histEnd   = $k - 1;

        if ($histEnd < $histStart) {
          continue;
        }

        $history = [];
        for ($h = $histStart; $h <= $histEnd; $h++) {
          $history[] = (int) $shifts[$indices[$h]]['scans'];
        }

        $baseline = $this->median($history);

        // Ignore too-low baselines (too noisy)
        if ($baseline < $minBaseline) {
          continue;
        }

        $threshold = $baseline * $ratioThreshold;

        if ($currentScans < $threshold) {
          $mad = $this->mad($history, $baseline);
          $deviation = $baseline - $currentScans;

          $severity = $this->classifySeverity($baseline, $currentScans, $mad);

          $anomalies[] = [
            'type'        => 'low_scan_shift_signal',
            'name'        => $name,
            'usage_id'    => (int) $current['usage_id'],
            'start'       => $current['start'],
            'end'         => $current['end'],
            'scans'       => $currentScans,
            'baseline'    => $baseline,
            'threshold'   => $threshold,
            'deviation'   => $deviation,
            'mad'         => $mad,
            'severity'    => $severity,
            'window_size' => count($history),
          ];
        }
      }
    }

    return $anomalies;
  }

  private function classifySeverity(float $baseline, int $currentScans, float $mad): string
  {
    // If MAD is ~0 (very stable history), fall back to ratio buckets.
    if ($mad <= 0.0001) {
      $ratio = $currentScans / max(1.0, $baseline);
      if ($ratio < 0.30) return 'critical';
      if ($ratio < 0.50) return 'warning';
      return 'info';
    }

    // Robust z-like score: (baseline - current) / MAD
    $score = ($baseline - $currentScans) / $mad;

    if ($score >= 4.0) return 'critical';
    if ($score >= 2.5) return 'warning';
    return 'info';
  }

  private function median(array $values): float
  {
    sort($values);
    $n = count($values);
    if ($n === 0) return 0.0;
    $mid = intdiv($n, 2);
    if ($n % 2 === 1) {
      return (float) $values[$mid];
    }
    return ((float) $values[$mid - 1] + (float) $values[$mid]) / 2.0;
  }

  private function mad(array $values, float $median): float
  {
    if (count($values) === 0) return 0.0;
    $dev = array_map(fn($v) => abs($v - $median), $values);
    return $this->median($dev);
  }
}
