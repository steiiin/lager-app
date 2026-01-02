<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ItemsStats extends Model
{

    protected $table = 'items_stats';
    protected $fillable = [ 'item_id', 'week_start', 'consumption_total', 'consumption_max', 'consumption_stddev', 'adjustment_total', 'booking_max', 'booking_count', 'aggregated_at' ];
    protected $casts = [ 'week_start' => 'date', 'aggregated_at' => 'datetime', 'consumption_stddev' => 'decimal:4' ];

    // ##################################################################################

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

}
