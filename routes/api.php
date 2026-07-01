<?php

use App\Http\Controllers\ApiOrderController;
use App\Http\Controllers\ItemExpiryController;
use App\Http\Controllers\InventoryController;
use Illuminate\Support\Facades\Route;

Route::get('/inventory-cache', [InventoryController::class, 'cache']);
Route::get('/inventory-jobs', [InventoryController::class, 'jobs']);

Route::post('/item-expiry', [ItemExpiryController::class, 'store']);
Route::put('/item-expiry/{id}/dismiss', [ItemExpiryController::class, 'dismiss']);
Route::put('/item-expiry/{id}', [ItemExpiryController::class, 'update']);
Route::delete('/item-expiry/{id}', [ItemExpiryController::class, 'destroy']);

Route::get('/order', [ ApiOrderController::class, 'create'] );
