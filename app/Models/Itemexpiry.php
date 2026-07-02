<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Itemexpiry extends Model
{

  protected $table = 'itemexpiry';
  protected $fillable = [ 'item_id', 'usage_id', 'expiryAt', 'expiryQuantity', 'status', 'is_ordered', 'note' ];
  protected $casts = [
    'expiryAt' => 'date',
    'expiryQuantity' => 'integer',
    'is_ordered' => 'boolean',
  ];
  protected $with = [ 'usage' ];

  public function item()
  {
    return $this->belongsTo(Item::class, 'item_id');
  }

  public function usage()
  {
    return $this->belongsTo(Usage::class, 'usage_id');
  }

}
