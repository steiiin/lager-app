<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Demand;
use App\Models\Item;
use App\Models\Itemsize;
use App\Models\Usage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;

class ApiStoreController extends Controller
{

    public function index()
    {

        $items = Item::withAll()->get();
        $usages = Usage::select(['id', 'name', 'is_locked'])->get();

        return response()->json([
            'usages' => $usages,
            'items' => $items,
        ]);

    }

}
