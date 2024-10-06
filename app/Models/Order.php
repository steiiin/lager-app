<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{

    protected $table = 'orders';
    protected $fillable = [
        'item_id',
        'prepare_time',
        'amount_desired',
        'amount_des_usage',
        'amount_des_changed',
        'amount_delivered',
        'is_order_open',
        'log'
    ];
    public $timestamps = false;

    protected $with = ['item', 'item.ordersize', 'item.basesize'];
    protected $casts = [
        'log' => 'array',  // Automatically cast 'location' to/from array (JSON)
    ];

    public function item()
    {
        return $this->belongsTo(Item::class, 'item_id');
    }

    // ##################################################################################

    public function scopeOpen($query)
    {
        return $query->where('is_order_open', true);
    }

    public function scopeClosed($query)
    {
        return $query->where('is_order_open', false);
    }

    public function scopeWithLogs($query)
    {
        return $query->where('is_order_open', false)->whereNotNull('logs');
    }

}
