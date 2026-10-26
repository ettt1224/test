<?php

use App\Http\Controllers\Api\ItemController;
use App\Http\Controllers\Api\LocationController;
use App\Http\Controllers\Api\MetaController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\StockController;
use App\Http\Controllers\Api\TransactionController;
use Illuminate\Support\Facades\Route;


Route::get('meta', MetaController::class);

// 1. 廠區儲位
Route::get('locations/tree', [LocationController::class, 'tree']);
Route::apiResource('locations', LocationController::class);

// 2. 物品主檔
Route::get('items/options', [ItemController::class, 'options']);
Route::get('items/import-template', [ItemController::class, 'template']);
Route::post('items/import', [ItemController::class, 'import']);
Route::get('items/{item}/sds', [ItemController::class, 'downloadSds']);
Route::delete('items/{item}/sds', [ItemController::class, 'deleteSds']);
Route::apiResource('items', ItemController::class);

// 4. 廠內作業紀錄 / 庫存
Route::apiResource('transactions', TransactionController::class)->except('update');
Route::get('stocks', [StockController::class, 'index']);
Route::post('stocks/rebuild', [StockController::class, 'rebuild']);

// 5. 政府申報
Route::get('reports/summary', [ReportController::class, 'summary']);
Route::get('reports/summary/export', [ReportController::class, 'export']);
