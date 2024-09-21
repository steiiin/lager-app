<?php

use App\Http\Controllers\BarcodeController;
use App\Http\Controllers\OrderController;
use Illuminate\Support\Facades\Route;

Route::get('/api-barcodes', [ BarcodeController::class, 'index' ]);

Route::get('/api-order-check', [ OrderController::class, 'check' ]);
Route::get('/api-order-execute', [ OrderController::class, 'execute' ]);
Route::get('/api-order-getlast', [ OrderController::class, 'getlast' ]);