<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class InventoryMaintenanceService
{
  public function __construct(private StatisticService $statisticService)
  {
  }

  public function run(): array
  {
    $runId = (string) Str::uuid();

    try {
      $oldThreshold = CarbonImmutable::now()->subMonths(6);

      $ordersDeleted = DB::table('orders')
        ->where('is_order_open', false)
        ->where('order_date', '<', $oldThreshold)
        ->delete();

      $bookingsDeleted = DB::table('bookings')
        ->where('created_at', '<', $oldThreshold)
        ->delete();

      $statsDeleted = DB::table('itemstats')
        ->where('aggregated_at', '<', $oldThreshold)
        ->delete();

      DB::statement('VACUUM');

      $this->statisticService->runWeeklyAggregation();

      return [
        'ok' => true,
        'run_id' => $runId,
        'message' => 'cleaned old data, vacuumed db and aggregated stats.',
        'counts' => [
          'orders_deleted' => $ordersDeleted,
          'bookings_deleted' => $bookingsDeleted,
          'stats_deleted' => $statsDeleted,
        ],
      ];
    } catch (\Throwable $e) {
      Log::error('Inventory.Jobs command failed.', [
        'run_id' => $runId,
        'exception' => $e,
      ]);

      return [
        'ok' => false,
        'run_id' => $runId,
        'error_code' => 'UNEXPECTED_ERROR',
        'message' => $e->getMessage(),
      ];
    }
  }
}
