<?php

use App\Http\Controllers\BookInController;
use App\Http\Controllers\BookOutController;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Session;
use Inertia\Inertia;

#region Welcome

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'isUnlocked' => Session::get('isUnlocked', false),
        'isTouchMode' => Config::get('app.app_touchmode', false),
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
    return Inertia::render('WhereIs');
})->name('whereis');

#endregion
#region Booking

Route::resource('/bookout', BookOutController::class);
Route::resource('/bookin', BookInController::class);

#endregion

require(dirname(__FILE__).'/config.php');