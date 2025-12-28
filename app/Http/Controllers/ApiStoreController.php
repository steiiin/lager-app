<?php

/**
 * ApiStoreController - controller
 *
 * Returns all available items and usages.
 *
 */
namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\Usage;

use Illuminate\Support\Facades\DB;
class ApiStoreController extends Controller
{

    public function index()
    {

        $items = Item::withPending()->get();
        $usages = Usage::select(['id', 'name', 'is_locked'])->get();

        return response()->json([
            'usages' => $usages,
            'items' => $items,
        ]);

    }

}
