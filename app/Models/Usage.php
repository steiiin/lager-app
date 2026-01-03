<?php

namespace App\Models;

use App\Services\BarcodeService;
use Illuminate\Database\Eloquent\Model;

class Usage extends Model
{

  protected $table = 'usages';
  protected $fillable = [ 'name' ];
  protected $appends = [ 'barcode' ];
  public $timestamps = false;

  // ##################################################################################

  public function getBarcodeAttribute()
  {
    return BarcodeService::generateUsage($this->id);
  }

}
