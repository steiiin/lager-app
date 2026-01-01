<?php

use App\Http\Controllers\ApiBarcodeController;
use App\Http\Controllers\ApiOrderController;
use App\Http\Controllers\ApiStatisticController;
use App\Http\Controllers\ApiStoreController;
use Illuminate\Support\Facades\Route;

Route::get('/store', [ApiStoreController::class, 'index']);
Route::get('/store-inventory', [ApiStoreController::class, 'inventory']);

Route::get('/order', [ApiOrderController::class, 'create']);

Route::get('/barcodes', [ ApiBarcodeController::class, 'index' ]);

Route::get('/statistic', [ApiStatisticController::class, 'index']);
Route::get('/logs', [ApiStatisticController::class, 'logs']);



// test

Route::get('/anforderung/pdf', [ApiOrderController::class, 'pdf'])
    ->name('anforderung.pdf');




Route::get('/order-check', [ApiOrderController::class, 'check']);
Route::get('/order-prepare', [ApiOrderController::class, 'prepare']);
Route::post('/order-execute', [ApiOrderController::class, 'execute']);
