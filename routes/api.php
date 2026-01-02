<?php

use App\Http\Controllers\ApiBarcodeController;
use App\Http\Controllers\ApiOrderController;
use App\Http\Controllers\InventoryController;
use Illuminate\Support\Facades\Route;

Route::get('/inventory-cache', [InventoryController::class, 'cache']);
Route::get('/inventory-jobs', [InventoryController::class, 'jobs']);

Route::get('/order', [ ApiOrderController::class, 'create'] );

Route::get('/barcodes', [ ApiBarcodeController::class, 'index' ]);
