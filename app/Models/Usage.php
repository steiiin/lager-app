<?php

namespace App\Models;

use App\Services\BarcodeGenerator;
use Illuminate\Database\Eloquent\Model;

class Usage extends Model
{

    protected $table = 'usages';
    protected $fillable = ['name', 'is_locked'];
    public $timestamps = false;
    protected $appends = ['barcode'];

    public function getBarcodeAttribute() 
    {
        return BarcodeGenerator::generateUsage($this->id);
    }

}
