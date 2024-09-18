<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Demand;
use App\Models\Item;
use App\Models\Itemsize;
use App\Models\Usage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use Inertia\Inertia;

class BookOutController extends Controller
{

    public function index(Request $request)
    {

        $usageId = $request->query('usageId', null);
        $usageId = !$usageId ? null : intval($usageId);

        $items = Item::with(['demand', 'sizes', 'basesize'])->get();
        $usages = Usage::all(['id', 'name', 'is_locked']);

        return Inertia::render('BookOut', [
            'items' => $items,
            'usages' => $usages,
            'isUnlocked' => Session::get('isUnlocked', false),
            'usageId' => $usageId
        ]);
        
    }

    public function store(Request $request)
    {

        $request->validate([
            'usage_id' => 'required|integer|exists:usages,id',
            'entries' => 'required|array',
            'entries.*.item_id' => 'required|integer|exists:items,id',
            'entries.*.item_amount' => 'required|integer|min:1'
        ]);

        try
        {

            DB::transaction(function () use ($request) 
            {

                $usageId = $request['usage_id'];
                $entries = $request['entries'];

                foreach($entries as $entry)
                {

                    Booking::create([
                        'usage_id' => $usageId,
                        'item_id' => $entry['item_id'],
                        'item_amount' => $entry['item_amount']
                    ]);

                    $item = Item::findOrFail($entry['item_id']);
                    $item->decrement('current_quantity', $entry['item_amount']);

                }

            });

            return redirect()->route('welcome');

        }
        catch (\Illuminate\Database\QueryException $e)
        {
            throw $e;
        }

    }

}
