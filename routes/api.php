<?php

use App\Http\Controllers\BarcodeController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\StatisticController;
use Illuminate\Support\Facades\Route;

Route::get('/barcodes', [ BarcodeController::class, 'index' ]);
/* Lager-Barcodes-Connector -- Access */

Route::get('/order-check', [OrderController::class, 'check']);
Route::get('/order-prepare', [OrderController::class, 'prepare']);
Route::post('/order-execute', [OrderController::class, 'execute']);
/* Lager-Bestell-Konnektor -- Access */

Route::get('/statistic', [StatisticController::class, 'index']);
Route::get('/logs-get', [StatisticController::class, 'logs']);
Route::post('/logs-truncate', [StatisticController::class, 'truncate']);
/* Lager-Stats-Konnektor -- Access */