<?php

namespace App\Http\Controllers;

use App\Models\Demand;
use App\Models\Item;
use App\Models\Itemsize;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class WhereIsController extends Controller
{

    public function index()
    {
        
        $items = Item::with(['demand'])->get();
        return Inertia::render('WhereIs', [
            'items' => $items,
        ]);
        
    }

}
