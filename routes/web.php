<?php

use App\Http\Controllers\BookInController;
use App\Http\Controllers\BookOutController;
use App\Models\Item;
use App\Models\Usage;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Session;
use Inertia\Inertia;

#region Welcome

Route::get('/', function () {

    $usages = Usage::all(['id', 'name', 'is_locked']);
    return Inertia::render('Welcome', [
        'usages' => $usages,
        'isUnlocked' => Session::get('isUnlocked', false),
    ]);

})->name('welcome');

Route::post('/', function(Illuminate\Http\Request $request) {

    $action = $request->input('action', '');
    if ($action === 'UNLOCK')
    {
        // load passwords
        $passKey = Config::get('auth.unlock_password', null);
        $passInput = $request->input('password');

        // verify & update session
        $isUnlocked = $passKey !== null && $passInput === $passKey;
        Session::put('isUnlocked', $isUnlocked);

    }
    else if ($action === 'LOCK')
    {
        // clear session
        Session::put('isUnlocked', false);
    }

    // interia respone
    return redirect()->route('welcome');

});

#endregion
#region WhereIs

Route::get('/whereis', function () {

    $items = Item::with(['demand'])->get();
    return Inertia::render('WhereIs', [
        'items' => $items,
    ]);

})->name('whereis');

#endregion
#region Booking

Route::resource('/bookout', BookOutController::class);
Route::resource('/bookin', BookInController::class);

#endregion

require(dirname(__FILE__).'/config.php');