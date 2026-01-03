<?php

namespace App\Models;

use App\Services\BarcodeService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Item extends Model
{

  protected $table = 'items';
  protected $fillable = ['name', 'location', 'name_alt', 'search_size', 'demand_id', 'min_stock', 'max_stock', 'onvehicle_stock', 'current_expiry', 'current_quantity', 'checked_at', 'max_order_quantity', 'max_bookin_quantity'];
  protected $casts = [ 'location' => 'array' ];
  protected $with = [ 'demand', 'sizes', 'basesize' ];
  protected $appends = ['barcodes', 'pending_quantity', 'has_stats' ];

  // ##################################################################################

  public function demand()
  {
    return $this->belongsTo(Demand::class, 'demand_id');
  }

  // ##################################################################################

  public function sizes()
  {
    return $this->hasMany(Itemsize::class, 'item_id');
  }

  public function basesize()
  {
    return $this->hasOne(Itemsize::class, 'item_id')->where('amount', 1);
  }

  public function ordersize()
  {
    return $this->hasOne(Itemsize::class, 'item_id')->where('is_default', true);
  }

  // ##################################################################################

  public function openOrders()
  {
    return $this->hasMany(Order::class, 'item_id')->where('is_order_open', true);
  }

  public function bookings()
  {
    return $this->hasMany(Booking::class, 'item_id');
  }

  public function weekStats()
  {
    return $this->hasMany(ItemsStats::class, 'item_id');
  }

  // ##################################################################################

  public function scopeWithPending($query)
  {
    return $query->with(['openOrders'])
      ->withSum('openOrders', 'amount_desired');
  }

  public function scopeWithStatistic($query)
  {

    $recentWeeks = 6;
    $cutoff = CarbonImmutable::now()
      ->startOfWeek(CarbonImmutable::MONDAY)
      ->subWeeks($recentWeeks)
      ->toDateString();

    $sub = DB::table('itemstats as istts')
      ->selectRaw('istts.item_id as item_id')
      ->selectRaw('COUNT(*) as weeks_total')
      ->selectRaw("SUM(CASE WHEN istts.week_start >= ? THEN 1 ELSE 0 END) as weeks_recent", [$cutoff])
      ->selectRaw('SUM(istts.consumption_total) as consumption_total_sum')
      ->selectRaw("SUM(CASE WHEN istts.week_start >= ? THEN istts.consumption_total ELSE 0 END) as consumption_recent_sum", [$cutoff])
      ->selectRaw('SUM(istts.adjustment_total) as adjustment_total_sum')
      ->selectRaw("SUM(CASE WHEN istts.week_start >= ? THEN istts.adjustment_total ELSE 0 END) as adjustment_recent_sum", [$cutoff])
      ->selectRaw('MAX(istts.consumption_total) as consumption_week_max_total')
      ->selectRaw("MAX(CASE WHEN istts.week_start >= ? THEN istts.consumption_total ELSE NULL END) as consumption_week_max_recent", [$cutoff])
      ->selectRaw('MAX(istts.booking_max) as booking_max_total')
      ->selectRaw("
        sqrt(
          MAX(
            (AVG(istts.consumption_total * istts.consumption_total)
              - (AVG(istts.consumption_total) * AVG(istts.consumption_total))),
            0
          )
        ) as consumption_week_stddev_total
      ")
      ->groupBy('istts.item_id');

    return $query
      ->select('items.*')
      ->leftJoinSub($sub, 'stats', 'stats.item_id', '=', 'items.id')
      ->selectRaw('COALESCE(stats.weeks_total, 0) as weeks_total')
      ->selectRaw('COALESCE(stats.weeks_recent, 0) as weeks_recent')
      ->selectRaw('COALESCE(stats.consumption_total_sum, 0) as consumption_total_sum')
      ->selectRaw('COALESCE(stats.consumption_recent_sum, 0) as consumption_recent_sum')
      ->selectRaw('COALESCE(stats.adjustment_total_sum, 0) as adjustment_total_sum')
      ->selectRaw('COALESCE(stats.adjustment_recent_sum, 0) as adjustment_recent_sum')
      ->selectRaw('COALESCE(stats.consumption_week_max_total, 0) as consumption_week_max_total')
      ->selectRaw('COALESCE(stats.consumption_week_max_recent, 0) as consumption_week_max_recent')
      ->selectRaw('COALESCE(stats.booking_max_total, 0) as booking_max_total')
      ->selectRaw('COALESCE(stats.consumption_week_stddev_total, 0) as consumption_week_stddev_total');

  }

  // ##################################################################################

  public function getBarcodesAttribute()
  {
    $sizes = $this->sizes;
    $codes = [];
    foreach ($sizes as $size) {
      $codes[BarcodeService::generateItem($this->id, $size->id)] = $size->amount;
    }
    return $codes;
  }

  public function getPendingQuantityAttribute()
  {
    $openOrdersTotal = $this->open_orders_sum_amount_desired ?? 0;
    return $this->current_quantity + $openOrdersTotal;
  }

  // ##################################################################################

  public function getHasStatsAttribute()
  {
    return ($this->weeks_total ?? 0) > 0;
  }

  public function appendStats(): self
  {
    $this->append([
      'consumption_per_week_total',
      'consumption_per_week_recent',
      'consumption_trend',
      'adjustment_per_week_total',
      'adjustment_per_week_recent',
      'adjustment_trend',
      'is_problem_item',
    ]);
    return $this;
  }

  public function getConsumptionPerWeekTotalAttribute(): float
  {
      $weeks = max(1, (int) ($this->weeks_total ?? 0));
      return (float) ($this->consumption_total_sum ?? 0) / $weeks;
  }

  public function getConsumptionPerWeekRecentAttribute(): float
  {
    $weeks = max(1, (int) ($this->weeks_recent ?? 0));
    return (float) ($this->consumption_recent_sum ?? 0) / $weeks;
  }

  public function getConsumptionTrendAttribute(): float
  {
    return $this->consumption_per_week_recent - $this->consumption_per_week_total;
  }

  public function getAdjustmentPerWeekTotalAttribute(): float
  {
    $weeks = max(1, (int) ($this->weeks_total ?? 0));
    return (float) ($this->adjustment_total_sum ?? 0) / $weeks;
  }

  public function getAdjustmentPerWeekRecentAttribute(): float
  {
    $weeks = max(1, (int) ($this->weeks_recent ?? 0));
    return (float) ($this->adjustment_recent_sum ?? 0) / $weeks;
  }

  public function getAdjustmentTrendAttribute(): float
  {
    return $this->adjustment_per_week_recent - $this->adjustment_per_week_total;
  }

  public function getIsProblemItemAttribute(): bool
  {
    $adj = (float) ($this->adjustment_per_week_recent ?? 0);
    $std = (float) ($this->consumption_week_stddev_total ?? 0);
    return $std > 0 && abs($adj) > $std;
  }

}
