<?php

use App\Http\Controllers\ApiBarcodeController;
use App\Http\Controllers\ApiOrderController;
use App\Http\Controllers\ApiStatisticController;
use App\Http\Controllers\ApiStoreController;
use Illuminate\Support\Facades\Route;

Route::get('/store', [ApiStoreController::class, 'index']);

Route::get('/barcodes', [ ApiBarcodeController::class, 'index' ]);

Route::get('/order-check', [ApiOrderController::class, 'check']);
Route::get('/order-prepare', [ApiOrderController::class, 'prepare']);
Route::post('/order-execute', [ApiOrderController::class, 'execute']);

Route::get('/statistic', [ApiStatisticController::class, 'index']);
Route::post('/logs', [ApiStatisticController::class, 'logs']);