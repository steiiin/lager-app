<?php

use App\Http\Controllers\ConfigDemandsController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\ConfigUsagesController;
use App\Http\Middleware\EnsureUnlocked;
use Illuminate\Support\Facades\Route;

Route::resource('/config-usages', ConfigUsagesController::class)->middleware(EnsureUnlocked::class);
Route::resource('/config-demands', ConfigDemandsController::class)->middleware(EnsureUnlocked::class);
Route::resource('/inventory', InventoryController::class)->middleware(EnsureUnlocked::class);