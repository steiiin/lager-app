<?php

use App\Http\Controllers\BookInController;
use App\Http\Controllers\BookOutController;
use App\Http\Controllers\ConfigDemandsController;
use App\Http\Controllers\ConfigUsagesController;
use App\Http\Controllers\InventoryController;
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
#region WhereIs

Route::get('/whereis', function () {
  return Inertia::render('WhereIs');
})->name('whereis');

#endregion
#region Booking

Route::resource('/bookout', BookOutController::class);
Route::resource('/bookin', BookInController::class);

#endregion
#region Inventory

Route::resource('/config-usages', ConfigUsagesController::class);
Route::resource('/config-demands', ConfigDemandsController::class);
Route::resource('/inventory', InventoryController::class);

#endregion
