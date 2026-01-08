<?php

use App\Http\Controllers\BookInController;
use App\Http\Controllers\BookOutController;
use App\Http\Controllers\InventoryDemandsController;
use App\Http\Controllers\InventoryInsightsController;
use App\Http\Controllers\InventoryUsagesController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\InventoryLabelsController;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

#region Welcome

Route::get('/', function (Request $request) {
  return Inertia::render('Welcome', [
    'isTouchMode' => Config::get('app.app_touchmode', false),
  ]);
})->name('welcome');

Route::post('/', function (Request $request) {

  $action = $request->input('action', '');
  if ($action === 'UNLOCK') {
    $passKey   = Config::get('auth.unlock_password');
    $passInput = $request->input('password');
    if ($passKey !== null && hash_equals((string) $passKey, (string) $passInput)) {
      return Redirect::route('inventory.index');
    }
  }

  throw ValidationException::withMessages([ 'password' => 'wrong password' ]);

});

#endregion
#region Booking

Route::resource('/bookout', BookOutController::class);
Route::resource('/bookin', BookInController::class);

#endregion
#region Inventory

Route::resource('/inventory-usages', InventoryUsagesController::class);
Route::resource('/inventory-demands', InventoryDemandsController::class);
Route::resource('/inventory-labels', InventoryLabelsController::class);
Route::resource('/inventory-insights', InventoryInsightsController::class)->only(['index']);
Route::resource('/inventory', InventoryController::class);

#endregion
