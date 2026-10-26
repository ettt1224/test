<?php

use Illuminate\Support\Facades\Route;

// 前端為 Vue SPA（vue-router history 模式），api/ 以外的路徑都回傳同一頁
Route::view('/{any?}', 'app')->where('any', '^(?!api/).*$');
