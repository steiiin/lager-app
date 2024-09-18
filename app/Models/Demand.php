<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Demand extends Model
{

    protected $table = 'demands';
    protected $fillable = [
        'name',
        'sp_name',
    ];

}
