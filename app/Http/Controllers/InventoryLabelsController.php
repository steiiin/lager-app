<?php

/**
 * InventoryLabelssController - controller
 *
 * Controller for InventoryLabelss page.
 *
 */

namespace App\Http\Controllers;

use App\Models\Demand;
use App\Models\Item;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class InventoryLabelsController extends Controller
{

  public function index()
  {
    $demands = Demand::all();
    return Inertia::render('InventoryLabels', [
      'demands' => $demands
    ]);
  }

  public function store(Request $request)
  {

    $request->validate([
      'name' => [ 'required', 'string', 'max:255',
        Rule::unique('demands')
          ->where(fn ($query) => $query->whereRaw('LOWER(name) = ?', [strtolower(request('name'))])) ]],
    [ 'name.unique' => 'Diese Anforderung gibt es schon.' ]);

    Demand::create($request->all());
    return redirect()->route('inventory-demands.index');
  }

  public function update(Request $request, $id)
  {

    $request->validate([
      'name' => [ 'required', 'string', 'max:255',
        Rule::unique('demands')
          ->ignore($id)
          ->where(fn ($query) => $query->whereRaw('LOWER(name) = ?', [strtolower(request('name'))])) ]],
      [ 'name.unique' => 'Diese Anforderung gibt es schon.' ]
    );

    $demand = Demand::findOrFail($id);
    $demand->update($request->all());

    return redirect()->route('inventory-demands.index');
  }

  public function destroy($id)
  {

    $isUsed = Item::where('demand_id', $id)->exists();
    if ($isUsed) {
      throw ValidationException::withMessages([
        'demand' => ['Diese Anforderung wird noch für einen Artikel benutzt.'],
      ]);
    }

    $demand = Demand::findOrFail($id);
    $demand->delete();

    return redirect()->route('inventory-demands.index');
  }
}
