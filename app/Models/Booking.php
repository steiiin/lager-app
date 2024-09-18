<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Booking extends Model
{

    protected $table = 'bookings';
    protected $fillable = [
        'usage_id',
        'item_id',
        'item_amount'
    ];

    public function item()
    {
        return $this->belongsTo(Item::class, 'item_id');
    }

    public function usage()
    {
        return $this->belongsTo(Usage::class, 'usage_id');
    }

}
