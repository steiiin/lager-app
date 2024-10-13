<?php

namespace App\Models;

use App\Services\BarcodeService;
use Illuminate\Database\Eloquent\Model;

class Usage extends Model
{

    protected $table = 'usages';
    protected $fillable = ['name', 'is_locked'];
    public $timestamps = false;
    protected $appends = ['barcode'];

    public function getBarcodeAttribute()
    {
        return BarcodeService::generateUsage($this->id);
    }

    public static function getInternalUsageName(int $id)
    {
        if ($id === -1) { return "Inv-Abweichung"; }
        else { return "Inv-Andere"; }
    }

}
