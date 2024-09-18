<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Itemsize extends Model
{

    protected $table = 'itemsizes';
    protected $fillable = ['item_id', 'unit', 'amount', 'is_default'];

}
