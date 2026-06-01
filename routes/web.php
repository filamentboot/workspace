<?php

use App\Http\Controllers\OfficialMarketIndexController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/plugin-market/index.json', OfficialMarketIndexController::class)
    ->name('plugin-market.index');
