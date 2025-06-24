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

    public static function getUsageName(Booking $booking)
    {
        return ($booking->usage_id < 0 || !$booking->usage) ? Usage::getInternalUsageName($booking->usage_id) : $booking->usage->name;
    }

    public static function getInternalUsageName(int $id)
    {
        if ($id === -1) { return "Inv-Abweichung"; }
        else if ($id === -2) { return "Inv-Rückbuchung"; }
        else if ($id === -3) { return "Inv-Verfall"; }
        else { return "Inv-Andere"; }
    }

}
