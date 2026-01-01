<?php

namespace App\Models;

use App\Services\BarcodeService;
use Illuminate\Database\Eloquent\Model;

class Item extends Model
{

    protected $table = 'items';
    protected $fillable = ['name', 'location', 'search_altnames', 'search_tags', 'demand_id', 'min_stock', 'max_stock', 'onvehicle_stock', 'current_expiry', 'current_quantity', 'checked_at', 'max_order_quantity', 'max_bookin_quantity'];
    protected $casts = [ 'location' => 'array' ];
    protected $with = [ 'demand', 'sizes', 'basesize' ];
    protected $appends = [ 'barcodes', 'pending_quantity' ];

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

    // ##################################################################################

    public function scopeWithPending($query)
    {
        return $query->with(['openOrders'])
            ->withSum('openOrders', 'amount_desired');
    }

    public function scopeWithStatistic($query)
    {

    }

    // ##################################################################################

    public function getBarcodesAttribute()
    {
        $sizes = $this->sizes;
        $codes = [];
        foreach($sizes as $size)
        {
            $codes[BarcodeService::generateItem($this->id, $size->id)] = $size->amount;
        }
        return $codes;
    }

    public function getPendingQuantityAttribute()
    {
        $openOrdersTotal = $this->open_orders_sum_amount_desired ?? 0;
        return $this->current_quantity + $openOrdersTotal;
    }

}
