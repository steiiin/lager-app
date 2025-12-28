<?php

/**
 * ConfigDemandsController - controller
 *
 * Controller for ConfigDemands page.
 *
 */

namespace App\Http\Controllers;

use App\Models\Demand;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class ConfigDemandsController extends Controller
{

    public function index()
    {
        $demands = Demand::all();
        return Inertia::render('ConfigDemands', [
            'demands' => $demands
        ]);
    }

    public function store(Request $request)
    {

        $request->validate([
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('demands')->where(function ($query) {
                    return $query->whereRaw('LOWER(name) = ?', [strtolower(request('name'))]);
                })
            ],
        ], [
            'name.unique' => 'Diese Anforderung gibt es schon.',
        ]);

        Demand::create($request->all());
        return redirect()->route('config-demands.index');

    }

    public function update(Request $request, $id)
    {

        $request->validate([
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('demands')->ignore($id)->where(function ($query) {
                    return $query->whereRaw('LOWER(name) = ?', [strtolower(request('name'))]);
                })
            ],
        ],
        [
            'name.unique' => 'Diese Anforderung gibt es schon.',
        ]);

        $demand = Demand::findOrFail($id);
        $demand->update($request->all());
        return redirect()->route('config-demands.index');

    }

    public function destroy($id)
    {
        $demand = Demand::findOrFail($id);
        $demand->delete();

        // TODO: only remove, if currently not in Booking-Table

        return redirect()->route('config-demands.index');
    }

}
