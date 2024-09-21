<?php

use App\Http\Controllers\BookInController;
use App\Http\Controllers\BookOutController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\WhereIsController;
use App\Http\Middleware\EnsureUnlocked;
use App\Models\Usage;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Session;
use Inertia\Inertia;

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

Route::resource('/whereis', WhereIsController::class);
Route::resource('/bookout', BookOutController::class);
Route::resource('/bookin', BookInController::class);

require(dirname(__FILE__).'/config.php');
require(dirname(__FILE__).'/api.php');