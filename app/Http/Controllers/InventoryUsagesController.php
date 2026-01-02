<?php

/**
 * InventoryUsagesController - controller
 *
 * Controller for InventoryUsages page.
 *
 */

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Usage;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class InventoryUsagesController extends Controller
{

  public function index()
  {
    $usages = Usage::all();
    return Inertia::render('InventoryUsages', [
      'usages' => $usages
    ]);
  }

  public function store(Request $request)
  {

    $request->validate([
      'name' => [ 'required', 'string', 'max:255',
        Rule::unique('usages')
          ->where(fn ($query) => $query->whereRaw('LOWER(name) = ?', [strtolower(request('name'))])) ]],
    [ 'name.unique' => 'Diese Verwendung gibt es schon.' ]);

    Usage::create($request->all());
    return redirect()->route('inventory-usages.index');
  }

  public function update(Request $request, $id)
  {

    $request->validate([
      'name' => [ 'required', 'string', 'max:255',
        Rule::unique('usages')
          ->ignore($id)
          ->where(fn ($query) => $query->whereRaw('LOWER(name) = ?', [strtolower(request('name'))])) ]],
      [ 'name.unique' => 'Diese Verwendung gibt es schon.' ]
    );

    $usage = Usage::findOrFail($id);
    $usage->update($request->all());

    return redirect()->route('inventory-usages.index');
  }

  public function destroy($id)
  {

    $isUsed = Booking::where('usage_id', $id)->exists();
    if ($isUsed) {
      throw ValidationException::withMessages([
        'usage' => ['Diese Verwendung wird noch in der Buchungs-Tabelle benutzt.'],
      ]);
    }

    $usage = Usage::findOrFail($id);
    $usage->delete();

    return redirect()->route('inventory-usages.index');
  }
}
