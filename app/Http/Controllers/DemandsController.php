<?php

namespace App\Http\Controllers;

use App\Models\Demand;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class DemandsController extends Controller
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
            'sp_name' => [
                'required',
                'string',
            ],
        ], [
            'name.unique' => 'Diese Anforderung gibt es schon.',
            'sp_name.required' => 'Du musst eine Sharepoint-Anforderung angeben.',
        ]);

        try
        {
            Demand::create($request->all());
            return redirect()->route('config-demands.index');
        }
        catch (\Illuminate\Database\QueryException $e)
        {
            throw $e;
        }

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
            'sp_name' => [
                'required',
                'string',
            ],
        ], [
            'name.unique' => 'Diese Anforderung gibt es schon.',
            'sp_name.required' => 'Du musst eine Sharepoint-Anforderung angeben.',
        ]);

        try
        {
            $demand = Demand::findOrFail($id);
            $demand->update($request->all());
            return redirect()->route('config-demands.index');
        }
        catch (\Illuminate\Database\QueryException $e)
        {
            throw $e;
        }
    }

    public function destroy($id)
    {
        $demand = Demand::findOrFail($id);
        $demand->delete();

        // TODO: only remove, if currently not in Booking-Table

        return redirect()->route('config-demands.index');
    }

}
