<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{

    protected $table = 'orders';
    protected $fillable = [
        'item_id',
        'amount_desired',
        'amount_delivered',
        'is_order_open',
        'booking_log'
    ];

    public function item()
    {
        return $this->belongsTo(Item::class, 'item_id');
    }

}
