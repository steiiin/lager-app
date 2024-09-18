<?php

use App\Http\Controllers\BarcodeController;
use App\Http\Controllers\OrderController;
use Illuminate\Support\Facades\Route;

Route::get('/api-barcodes', [ BarcodeController::class, 'index' ]);
Route::get('/api-order-prepare', [ OrderController::class, 'index' ]);