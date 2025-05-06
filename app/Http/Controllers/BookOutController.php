<?php


/**
 * BookOutController - controller
 *
 * Controller for BookOut page.
 *
 */

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Item;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class BookOutController extends Controller
{

    public function index(Request $request)
    {

        $usageId = $request->query('usageId', null);
        $usageId = !$usageId ? null : intval($usageId);

        return Inertia::render('BookOut', [
            'isUnlocked' => Session::get('isUnlocked', false),
            'usageId' => $usageId
        ]);

    }

    public function store(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'usage_id'               => 'required|integer',
            'entries'                => 'required|array',
            'entries.*.item_id'      => 'required|integer|exists:items,id',
            'entries.*.item_amount'  => 'required|integer|min:1',
        ]);
        $validator->sometimes(
            'usage_id',
            Rule::in([-2, -3]),
            fn($input) => $input->usage_id < 0
        );
        $validator->sometimes(
            'usage_id',
            'exists:usages,id',
            fn($input) => $input->usage_id >= 0
        );
        $validator->validate();

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

}
