<?php

/**
 * InventoryController - controller
 *
 * Controller for Inventory page.
 *
 */

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Demand;
use App\Models\Item;
use App\Models\Itemsize;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class InventoryController extends Controller
{

    public function index()
    {

        $demands = Demand::all(['id', 'name']);
        return Inertia::render('Inventory', [
            'demands' => $demands,
        ]);

    }

    public function store(Request $request)
    {

        $request->validate([
            'id' => 'nullable',
            'name' => [
                'required', 'string', 'max:255'
            ],
            'search_altnames' => 'nullable|string',
            'search_tags' => 'nullable|string',
            'demand_id' => 'required|integer|exists:demands,id',
            'location' => 'required|array',
            'location.room' => 'nullable|string',
            'location.cab' => 'nullable|string',
            'location.exact' => 'nullable|string',
            'min_stock' => 'required|numeric|min:0|max:999',
            'max_stock' => 'required|numeric|min:0|max:999|gte:min_stock',
            'onvehicle_stock' => 'required|numeric|min:0|max:999',

            'sizes' => 'required|array',
            'sizes.*.id' => 'nullable|integer|exists:itemsizes,id',
            'sizes.*.unit' => 'required|string',
            'sizes.*.amount' => 'required|numeric|min:1|max:999',
            'sizes.*.is_default' => 'required|boolean',
        ]);

        DB::transaction(function () use ($request)
        {

            // create item
            $newItem = Item::create($request->except('sizes'));

            // handle sizes
            $this->handleSizes($request->input('sizes'), $newItem);

        });
        return redirect()->route('inventory.index');

    }

    public function update(Request $request, $id)
    {

        $request->validate([
            'id' => 'nullable',
            'name' => [
                'required', 'string', 'max:255'
            ],
            'search_altnames' => 'nullable|string',
            'search_tags' => 'nullable|string',
            'demand_id' => 'required|integer|exists:demands,id',
            'location' => 'required|array',
            'location.room' => 'nullable|string',
            'location.cab' => 'nullable|string',
            'location.exact' => 'nullable|string',
            'min_stock' => 'required|numeric|min:0|max:999',
            'max_stock' => 'required|numeric|min:0|max:999|gte:min_stock',
            'onvehicle_stock' => 'required|numeric|min:0|max:999',

            'sizes' => 'required|array',
            'sizes.*.id' => 'nullable|integer|exists:itemsizes,id',
            'sizes.*.unit' => 'required|string',
            'sizes.*.amount' => 'required|numeric|min:1|max:999',
            'sizes.*.is_default' => 'required|boolean',

            'max_order_quantity' => 'required|numeric|min:0|max:999',
        ]);

        DB::transaction(function () use ($request, $id)
        {

            // get item
            $item = Item::findOrFail($id);

            // update item
            $item->update($request->except('sizes'));

            // handle sizes
            $this->handleSizes($request->input('sizes'), $item);

            // handle check
            $this->handleCheck($item);

            // handle stockchange
            $this->handleStockChange($request, $item);

        });

        return redirect()->route('inventory.index');

    }

    private function handleSizes(Array $sizes, Item $item)
    {
        if ($sizes)
        {

            // Check if any 'is_default' is true
            $hasOrderSize = collect($sizes)->contains(function ($size) {
                return isset($size['is_default']) && $size['is_default'] == true;
            });
            if (!$hasOrderSize)
            {

                // Find the item where 'amount' === 1 and set 'is_default' to true
                foreach ($sizes as &$size) {
                    if ($size['amount'] == 1) {
                        $size['is_default'] = true;
                        break; // Exit the loop after setting the first match
                    }
                }
                unset($size); // Break the reference with the last element

            }

            foreach ($sizes as $size) {

                if ($size['id'] === null)
                {
                    Itemsize::create(array_merge($size, [ 'item_id' => $item->id ]));
                }
                else
                {
                    $itemsize = Itemsize::findOrFail($size['id']);
                    $itemsize->update($size);
                }

            }

        }
    }

    private function handleCheck(Item $item)
    {
        $item->touch('checked_at');
    }

    private function handleStockChange(Request $request, Item $item)
    {
        $change = $item->current_quantity - $request->current_quantity;
        if ($change !== 0)
        {
            Booking::create([
                'usage_id' => $request->stockchangeReason,
                'item_id' => $item->id,
                'item_amount' => $change
            ]);
        }
    }

    public function destroy($id)
    {

        DB::transaction(function () use ($id)
        {

            $item = Item::findOrFail($id);
            $item->delete();

            Itemsize::where('item_id', $item->id)->delete();

            // TODO: only if not on booking

        });

        return redirect()->route('inventory.index');


        $item = Item::findOrFail($id);
        $demand->delete();

        // TODO: only remove, if currently not in Booking-Table

        return redirect()->route('config-demands.index');
    }

}
