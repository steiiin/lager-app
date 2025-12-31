<?php

/**
 * ConfigUsagesController - controller
 *
 * Controller for ConfigUsages page.
 *
 */

namespace App\Http\Controllers;

use App\Models\Usage;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class ConfigUsagesController extends Controller
{

    public function index()
    {
        $usages = Usage::all();
        return Inertia::render('ConfigUsages', [
            'usages' => $usages
        ]);
    }

    public function store(Request $request)
    {

        $request->validate([
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('usages')->where(function ($query) {
                    return $query->whereRaw('LOWER(name) = ?', [strtolower(request('name'))]);
                }),
            ],
        ], [
            'name.unique' => 'Diese Verwendung gibt es schon.',
        ]);

        Usage::create($request->all());
        return redirect()->route('config-usages.index');

    }

    public function update(Request $request, $id)
    {

        $request->validate([
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('usages')->ignore($id)->where(function ($query) {
                    return $query->whereRaw('LOWER(name) = ?', [strtolower(request('name'))]);
                }),
            ],
        ], [
            'name.unique' => 'Diese Verwendung gibt es schon.',
        ]);

        $usage = Usage::findOrFail($id);
        $usage->update($request->all());
        return redirect()->route('config-usages.index');

    }

    public function destroy($id)
    {
        $usage = Usage::findOrFail($id);
        $usage->delete();

        // TODO: only remove, if currently not in Booking-Table

        return redirect()->route('config-usages.index');
    }

}
