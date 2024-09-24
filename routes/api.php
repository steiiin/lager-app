<?php

use App\Http\Controllers\BarcodeController;
use App\Http\Controllers\OrderController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/barcodes', [ BarcodeController::class, 'index' ]);
/* Lager-Barcodes-Connector -- Access */

Route::get('/order-check', [OrderController::class, 'check']);
Route::get('/order-prepare', [OrderController::class, 'prepare']);
Route::post('/order-execute', [OrderController::class, 'execute']);
/* Lager-Order-Connector -- Access */