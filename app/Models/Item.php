<?php

namespace App\Models;

use App\Services\BarcodeGenerator;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Item extends Model
{

    protected $table = 'items';
    protected $fillable = ['name', 'location', 'search_altnames', 'search_tags', 'demand_id', 'min_stock', 'max_stock', 'current_expiry', 'current_quantity'];
    protected $casts = [
        'location' => 'array',  // Automatically cast 'location' to/from array (JSON)
    ];

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

    protected $appends = ['barcodes'];

    public function getBarcodesAttribute() 
    {
        $sizes = $this->sizes;
        $codes = [];
        foreach($sizes as $size)
        {
            $codes[BarcodeGenerator::generateItem($this->id, $size->id)] = $size->amount;
        }
        return $codes;
    }

}
