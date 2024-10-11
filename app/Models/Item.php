<?php

namespace App\Models;

use App\Services\BarcodeService;
use Illuminate\Database\Eloquent\Model;

class Item extends Model
{

    protected $table = 'items';
    protected $fillable = ['name', 'location', 'search_altnames', 'search_tags', 'demand_id', 'min_stock', 'max_stock', 'current_expiry', 'current_quantity'];
    protected $casts = [
        'location' => 'array',  // Automatically cast 'location' to/from array (JSON)
    ];

    public function scopeWithAll($query)
    {
        return $query->withSum('openOrders', 'amount_desired')->with(['demand', 'sizes', 'basesize', 'ordersize']);
    }

    public function demand()
    {
        return $this->belongsTo(Demand::class, 'demand_id');
    }

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

    public function openOrders()
    {
        return $this->hasMany(Order::class, 'item_id')->where('is_order_open', true);
    }

    protected $appends = ['barcodes', 'demanded_quantity'];
    
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

    public function getDemandedQuantityAttribute()
    {
        $openOrdersTotal = $this->open_orders_sum_amount_desired ?? 0;
        return $this->current_quantity + $openOrdersTotal;
    }

    // ##################################################################################

    public function closedOrders()
    {
        return $this->hasMany(Order::class, 'item_id')->where('is_order_open', false);
    }

    public function scopeWithStats($query)
    {
        return $query->withSum('closedOrders', 'amount_desired')
            ->withSum('closedOrders', 'amount_des_usage')
            ->withSum('closedOrders', 'amount_des_changed');
    }

}
