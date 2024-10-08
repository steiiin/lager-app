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

    public static function getInternalUsageName(int $id)
    {
        if ($id === -1) { return "Inv-Abweichung"; }
        else if ($id === -2) { return "Inv-Verfall"; }
        else if ($id === -3) { return "Inv-Beschädigung"; }
        else { return "Inv-Andere"; }
    }

}
