<?php

namespace App\Models;

use App\Services\BarcodeService;
use Illuminate\Database\Eloquent\Model;

class Usage extends Model
{

  protected $table = 'usages';
  protected $fillable = [ 'name', 'could_expire' ];
  protected $appends = [ 'barcode' ];protected $casts = [
    'could_expire' => 'boolean',
  ];
  public $timestamps = false;

  // ##################################################################################

  public function getBarcodeAttribute()
  {
    return BarcodeService::generateUsage($this->id);
  }

}
