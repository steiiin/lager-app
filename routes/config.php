<?php

use App\Http\Controllers\DemandsController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\UsagesController;
use App\Http\Middleware\EnsureUnlocked;
use Illuminate\Support\Facades\Route;

Route::resource('/config-usages', UsagesController::class)->middleware(EnsureUnlocked::class);
Route::resource('/config-demands', DemandsController::class)->middleware(EnsureUnlocked::class);
Route::resource('/inventory', InventoryController::class)->middleware(EnsureUnlocked::class);
