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
